<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\Modul;
use App\Models\ProgressModul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModulController extends Controller
{
    /**
     * Daftar modul dalam kelas.
     */
    public function index(Request $request, int $kelasId): JsonResponse
    {
        $kelas  = Kelas::where('dosen_id', $request->user()->id)->findOrFail($kelasId);
        $moduls = Modul::where('kelas_id', $kelasId)
            ->with(['materis:id,modul_id,judul,tipe,urutan', 'tugass:id,modul_id,judul,batas_waktu'])
            ->orderBy('urutan')
            ->get();

        return response()->json(['moduls' => $moduls]);
    }

    /**
     * Buat modul baru dalam kelas.
     * Urutan otomatis ke urutan terakhir + 1.
     */
    public function store(Request $request, int $kelasId): JsonResponse
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)->findOrFail($kelasId);

        $request->validate([
            'judul'                    => 'required|string|max:255',
            'deskripsi'                => 'nullable|string',
            'urutan'                   => 'nullable|integer|min:1',
            'wajib_selesai_sebelumnya' => 'nullable|boolean',
        ]);

        $urutan = $request->urutan ?? (Modul::where('kelas_id', $kelasId)->max('urutan') + 1);

        $modul = Modul::create([
            'kelas_id'                 => $kelasId,
            'judul'                    => $request->judul,
            'deskripsi'                => $request->deskripsi,
            'urutan'                   => $urutan,
            'wajib_selesai_sebelumnya' => $request->wajib_selesai_sebelumnya ?? true,
        ]);

        // Inisialisasi progress modul untuk semua mahasiswa yang sudah enroll
        $this->inisialisasiProgressMahasiswa($modul, $kelas);

        AuditLog::catat('create_modul', 'Modul', "Buat modul '{$modul->judul}' di kelas '{$kelas->nama_kelas}'");

        return response()->json([
            'message' => 'Modul berhasil dibuat',
            'modul'   => $modul,
        ], 201);
    }

    /**
     * Detail modul.
     */
    public function show(Request $request, int $kelasId, int $id): JsonResponse
    {
        $modul = Modul::whereHas('kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->with(['materis', 'tugass'])
            ->where('kelas_id', $kelasId)
            ->findOrFail($id);

        return response()->json(['modul' => $modul]);
    }

    /**
     * Update modul.
     */
    public function update(Request $request, int $kelasId, int $id): JsonResponse
    {
        $modul = Modul::whereHas('kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->where('kelas_id', $kelasId)
            ->findOrFail($id);

        $request->validate([
            'judul'                    => 'sometimes|string|max:255',
            'deskripsi'                => 'sometimes|nullable|string',
            'urutan'                   => 'sometimes|integer|min:1',
            'is_active'                => 'sometimes|boolean',
            'wajib_selesai_sebelumnya' => 'sometimes|boolean',
        ]);

        $dataLama = $modul->toArray();
        $modul->update($request->only(['judul', 'deskripsi', 'urutan', 'is_active', 'wajib_selesai_sebelumnya']));

        AuditLog::catat('update_modul', 'Modul', "Update modul ID:{$id}", $dataLama, $modul->fresh()->toArray());

        return response()->json([
            'message' => 'Modul berhasil diperbarui',
            'modul'   => $modul->fresh(),
        ]);
    }

    /**
     * Hapus modul.
     */
    public function destroy(Request $request, int $kelasId, int $id): JsonResponse
    {
        $modul = Modul::whereHas('kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->where('kelas_id', $kelasId)
            ->findOrFail($id);

        AuditLog::catat('delete_modul', 'Modul', "Hapus modul '{$modul->judul}'", $modul->toArray());

        $modul->delete();

        return response()->json(['message' => 'Modul berhasil dihapus']);
    }

    // =============================================
    // PRIVATE HELPER
    // =============================================

    /** Buat record progress_modul kosong untuk semua mahasiswa yang sudah enroll */
    private function inisialisasiProgressMahasiswa(Modul $modul, Kelas $kelas): void
    {
        $mahasiswaIds = $kelas->mahasiswas()->pluck('users.id');

        foreach ($mahasiswaIds as $mahasiswaId) {
            ProgressModul::firstOrCreate([
                'mahasiswa_id' => $mahasiswaId,
                'modul_id'     => $modul->id,
            ], [
                'kelas_id'            => $kelas->id,
                'persentase'          => 0,
                'status'              => 'belum_mulai',
                'jumlah_materi_total' => 0,
            ]);
        }
    }
}
