<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    /**
     * Daftar kelas yang diajar oleh dosen yang login.
     */
    public function index(Request $request): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)
            ->withCount('mahasiswas as jumlah_mahasiswa')
            ->with('moduls:id,kelas_id,judul,urutan')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($kelas);
    }

    /**
     * Buat kelas online baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nama_kelas'      => 'required|string|max:255',
            'deskripsi'       => 'nullable|string',
            'semester'        => 'nullable|string|max:50',
            'tahun_ajaran'    => 'nullable|string|max:20',
            'tanggal_mulai'   => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after:tanggal_mulai',
            'thumbnail'       => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->except('thumbnail');
        $data['dosen_id'] = $request->user()->id;

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('thumbnail-kelas', 'public');
        }

        $kelas = Kelas::create($data);

        AuditLog::catat(
            'create_kelas',
            'Kelas',
            "Dosen {$request->user()->email} membuat kelas baru: {$kelas->nama_kelas} [{$kelas->kode_kelas}]",
            null,
            $kelas->toArray()
        );

        return response()->json([
            'message' => 'Kelas berhasil dibuat',
            'kelas'   => $kelas,
        ], 201);
    }

    /**
     * Detail kelas beserta modul, materi, dan statistik.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)
            ->with([
                'moduls.materis',
                'moduls.tugass',
                'mahasiswas:id,nama_lengkap,email,nim_nip',
            ])
            ->findOrFail($id);

        // Statistik kelas
        $statistik = [
            'total_mahasiswa' => $kelas->mahasiswas->count(),
            'total_modul'     => $kelas->moduls->count(),
            'total_materi'    => $kelas->moduls->sum(fn($m) => $m->materis->count()),
            'rata_nilai'      => $kelas->mahasiswas->avg('pivot.nilai_akhir'),
        ];

        return response()->json([
            'kelas'    => $kelas,
            'statistik'=> $statistik,
        ]);
    }

    /**
     * Update data kelas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'nama_kelas'      => 'sometimes|string|max:255',
            'deskripsi'       => 'sometimes|nullable|string',
            'semester'        => 'sometimes|nullable|string|max:50',
            'tahun_ajaran'    => 'sometimes|nullable|string|max:20',
            'tanggal_mulai'   => 'sometimes|nullable|date',
            'tanggal_selesai' => 'sometimes|nullable|date',
            'is_active'       => 'sometimes|boolean',
            'thumbnail'       => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $dataLama = $kelas->toArray();
        $data = $request->except('thumbnail');

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('thumbnail-kelas', 'public');
        }

        $kelas->update($data);

        AuditLog::catat('update_kelas', 'Kelas', "Update kelas ID:{$id}", $dataLama, $kelas->fresh()->toArray());

        return response()->json([
            'message' => 'Kelas berhasil diperbarui',
            'kelas'   => $kelas->fresh(),
        ]);
    }

    /**
     * Hapus kelas (soft delete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)->findOrFail($id);

        AuditLog::catat('delete_kelas', 'Kelas', "Hapus kelas: {$kelas->nama_kelas} [{$kelas->kode_kelas}]", $kelas->toArray());

        $kelas->delete();

        return response()->json(['message' => 'Kelas berhasil dihapus']);
    }

    /**
     * Daftarkan mahasiswa ke kelas (oleh dosen).
     */
    public function daftarkanMahasiswa(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'mahasiswa_ids' => 'required|array',
            'mahasiswa_ids.*' => 'exists:users,id',
        ]);

        $berhasil = [];
        $gagal = [];

        foreach ($request->mahasiswa_ids as $mahasiswaId) {
            $user = User::find($mahasiswaId);
            if ($user && $user->isMahasiswa()) {
                // Cek sudah terdaftar belum
                $sudahAda = $kelas->mahasiswas()->where('mahasiswa_id', $mahasiswaId)->exists();
                if (!$sudahAda) {
                    $kelas->mahasiswas()->attach($mahasiswaId, ['tanggal_bergabung' => now()]);
                    $berhasil[] = $user->nama_lengkap;
                } else {
                    $gagal[] = "{$user->nama_lengkap} (sudah terdaftar)";
                }
            }
        }

        AuditLog::catat(
            'daftarkan_mahasiswa',
            'Kelas',
            "Daftarkan " . count($berhasil) . " mahasiswa ke kelas {$kelas->nama_kelas}"
        );

        return response()->json([
            'message'  => count($berhasil) . ' mahasiswa berhasil didaftarkan',
            'berhasil' => $berhasil,
            'gagal'    => $gagal,
        ]);
    }

    /**
     * Statistik nilai dan analitik untuk dashboard dosen.
     */
    public function statistikNilai(Request $request, int $id): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)
            ->with(['mahasiswas:id,nama_lengkap,nim_nip'])
            ->findOrFail($id);

        // Distribusi nilai
        $distribusi = [
            'A (90-100)' => $kelas->mahasiswas()->wherePivotBetween('nilai_akhir', [90, 100])->count(),
            'B (80-89)'  => $kelas->mahasiswas()->wherePivotBetween('nilai_akhir', [80, 89])->count(),
            'C (70-79)'  => $kelas->mahasiswas()->wherePivotBetween('nilai_akhir', [70, 79])->count(),
            'D (60-69)'  => $kelas->mahasiswas()->wherePivotBetween('nilai_akhir', [60, 69])->count(),
            'E (<60)'    => $kelas->mahasiswas()->wherePivot('nilai_akhir', '<', 60)->count(),
            'Belum ada nilai' => $kelas->mahasiswas()->wherePivotNull('nilai_akhir')->count(),
        ];

        return response()->json([
            'kelas'     => $kelas->only(['id', 'nama_kelas', 'kode_kelas']),
            'distribusi_nilai' => $distribusi,
            'rata_rata' => round($kelas->mahasiswas->avg('pivot.nilai_akhir'), 2),
            'tertinggi' => $kelas->mahasiswas->max('pivot.nilai_akhir'),
            'terendah'  => $kelas->mahasiswas->min('pivot.nilai_akhir'),
        ]);
    }
}
