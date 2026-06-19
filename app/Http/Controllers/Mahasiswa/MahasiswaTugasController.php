<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\PengumpulanTugas;
use App\Models\ProgressModul;
use App\Models\Tugas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MahasiswaTugasController extends Controller
{
    /**
     * Submit/kumpulkan tugas oleh mahasiswa.
     * Menerima upload laporan (PDF/Word) + source code (zip).
     */
    public function submit(Request $request, int $tugasId): JsonResponse
    {
        $tugas = Tugas::with('modul.kelas')->findOrFail($tugasId);
        $mahasiswaId = $request->user()->id;

        // Pastikan mahasiswa terdaftar di kelas
        $terdaftar = $tugas->modul->kelas->mahasiswas()
            ->where('mahasiswa_id', $mahasiswaId)
            ->exists();

        if (!$terdaftar) {
            return response()->json(['message' => 'Anda tidak terdaftar di kelas ini.'], 403);
        }

        // Cek apakah modul dikunci (karena plagiarisme - dari Mhs5)
        $progress = ProgressModul::where('mahasiswa_id', $mahasiswaId)
            ->where('modul_id', $tugas->modul_id)
            ->first();

        if ($progress && $progress->status === 'dikunci') {
            return response()->json([
                'message' => 'Modul ini dikunci karena terindikasi plagiarisme. Hubungi dosen.',
            ], 403);
        }

        $request->validate([
            'file_laporan'    => 'required_if:wajib,true|nullable|file|mimes:pdf,doc,docx|max:10240',
            'file_source_code'=> 'nullable|file|mimes:zip,rar,tar|max:51200',
            'catatan'         => 'nullable|string|max:1000',
        ]);

        // Cek apakah sudah pernah mengumpulkan
        $percobaan = PengumpulanTugas::where('tugas_id', $tugasId)
            ->where('mahasiswa_id', $mahasiswaId)
            ->max('percobaan_ke') ?? 0;

        $data = [
            'tugas_id'         => $tugasId,
            'mahasiswa_id'     => $mahasiswaId,
            'catatan_mahasiswa'=> $request->catatan,
            'status'           => 'terkumpul',
            'dikumpulkan_pada' => now(),
            'percobaan_ke'     => $percobaan + 1,
            'terlambat'        => $tugas->batas_waktu && now()->gt($tugas->batas_waktu),
        ];

        if ($request->hasFile('file_laporan')) {
            $data['file_laporan'] = $request->file('file_laporan')
                ->store("tugas/{$tugasId}/laporan", 'public');
        }

        if ($request->hasFile('file_source_code')) {
            $data['file_source_code'] = $request->file('file_source_code')
                ->store("tugas/{$tugasId}/source-code", 'public');
        }

        $pengumpulan = PengumpulanTugas::create($data);

        AuditLog::catat(
            'submit_tugas',
            'Tugas',
            "Mahasiswa {$request->user()->email} mengumpulkan tugas ID:{$tugasId}, percobaan ke-{$data['percobaan_ke']}"
        );

        return response()->json([
            'message'      => 'Tugas berhasil dikumpulkan',
            'pengumpulan'  => $pengumpulan,
            'terlambat'    => $data['terlambat'],
        ], 201);
    }

    /**
     * Riwayat pengumpulan tugas mahasiswa.
     */
    public function riwayat(Request $request, int $tugasId): JsonResponse
    {
        $riwayat = PengumpulanTugas::where('tugas_id', $tugasId)
            ->where('mahasiswa_id', $request->user()->id)
            ->orderBy('percobaan_ke', 'desc')
            ->get();

        return response()->json(['riwayat' => $riwayat]);
    }

    /**
     * Dashboard mahasiswa: semua kelas + progress.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $mahasiswaId = $request->user()->id;

        $kelasAktif = Kelas::whereHas('mahasiswas', fn($q) => $q->where('mahasiswa_id', $mahasiswaId))
            ->with([
                'dosen:id,nama_lengkap',
                'moduls' => function ($q) use ($mahasiswaId) {
                    $q->with(['progressMahasiswas' => fn($p) => $p->where('mahasiswa_id', $mahasiswaId)]);
                },
            ])
            ->get();

        // Hitung total progress per kelas
        $kelasData = $kelasAktif->map(function ($kelas) use ($mahasiswaId) {
            $progressList = ProgressModul::where('mahasiswa_id', $mahasiswaId)
                ->where('kelas_id', $kelas->id)
                ->get();

            $totalModul     = $kelas->moduls->count();
            $modulSelesai   = $progressList->where('status', 'selesai')->count();
            $rataPersentase = $totalModul > 0
                ? round($progressList->avg('persentase'), 1)
                : 0;

            return [
                'kelas'          => $kelas->only(['id', 'nama_kelas', 'kode_kelas', 'thumbnail']),
                'dosen'          => $kelas->dosen->nama_lengkap,
                'total_modul'    => $totalModul,
                'modul_selesai'  => $modulSelesai,
                'progress_persen'=> $rataPersentase,
                'nilai_akhir'    => $kelas->pivot->nilai_akhir ?? null,
            ];
        });

        return response()->json([
            'mahasiswa'   => $request->user()->only(['id', 'nama_lengkap', 'nim_nip']),
            'total_kelas' => $kelasAktif->count(),
            'kelas'       => $kelasData,
        ]);
    }
}
