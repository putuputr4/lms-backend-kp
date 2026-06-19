<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Materi;
use App\Models\Modul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MateriController extends Controller
{
    /**
     * Tambah materi baru ke dalam modul.
     * Mendukung upload PDF, slide, atau input URL video/link.
     */
    public function store(Request $request, int $modulId): JsonResponse
    {
        $modul = Modul::whereHas('kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->findOrFail($modulId);

        $request->validate([
            'judul'        => 'required|string|max:255',
            'deskripsi'    => 'nullable|string',
            'tipe'         => 'required|in:pdf,video,slide,link,teks',
            'file'         => 'required_if:tipe,pdf,slide|file|mimes:pdf,ppt,pptx|max:20480',
            'url'          => 'required_if:tipe,video,link|nullable|url',
            'konten_teks'  => 'required_if:tipe,teks|nullable|string',
            'urutan'       => 'nullable|integer|min:1',
            'durasi_menit' => 'nullable|integer|min:1',
        ]);

        $data = $request->only(['judul', 'deskripsi', 'tipe', 'url', 'konten_teks', 'durasi_menit']);
        $data['modul_id'] = $modulId;

        // Tentukan urutan otomatis jika tidak diisi
        $data['urutan'] = $request->urutan ?? (Materi::where('modul_id', $modulId)->max('urutan') + 1);

        // Upload file
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store("materi/modul-{$modulId}", 'public');
            $data['file_path']   = $path;
            $data['ukuran_file'] = $request->file('file')->getSize();
        }

        $materi = Materi::create($data);

        // Update total materi di progress semua mahasiswa di kelas ini
        $this->updateTotalMateriProgress($modul);

        AuditLog::catat('create_materi', 'Materi', "Tambah materi '{$materi->judul}' ke modul ID:{$modulId}");

        return response()->json([
            'message' => 'Materi berhasil ditambahkan',
            'materi'  => $materi,
        ], 201);
    }

    /**
     * Update materi.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $materi = Materi::whereHas('modul.kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->findOrFail($id);

        $request->validate([
            'judul'        => 'sometimes|string|max:255',
            'deskripsi'    => 'sometimes|nullable|string',
            'url'          => 'sometimes|nullable|url',
            'konten_teks'  => 'sometimes|nullable|string',
            'urutan'       => 'sometimes|integer|min:1',
            'durasi_menit' => 'sometimes|nullable|integer',
            'is_active'    => 'sometimes|boolean',
            'file'         => 'sometimes|file|mimes:pdf,ppt,pptx|max:20480',
        ]);

        $dataLama = $materi->toArray();
        $data = $request->only(['judul', 'deskripsi', 'url', 'konten_teks', 'urutan', 'durasi_menit', 'is_active']);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store("materi/modul-{$materi->modul_id}", 'public');
            $data['file_path']   = $path;
            $data['ukuran_file'] = $request->file('file')->getSize();
        }

        $materi->update($data);

        AuditLog::catat('update_materi', 'Materi', "Update materi ID:{$id}", $dataLama, $materi->fresh()->toArray());

        return response()->json([
            'message' => 'Materi berhasil diperbarui',
            'materi'  => $materi->fresh(),
        ]);
    }

    /**
     * Hapus materi.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $materi = Materi::whereHas('modul.kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->findOrFail($id);

        $modul = $materi->modul;
        AuditLog::catat('delete_materi', 'Materi', "Hapus materi '{$materi->judul}'", $materi->toArray());

        $materi->delete();

        // Update ulang total materi di progress
        $this->updateTotalMateriProgress($modul);

        return response()->json(['message' => 'Materi berhasil dihapus']);
    }

    /**
     * Mahasiswa menandai materi sebagai sudah dibaca.
     * Otomatis update progress modul.
     */
    public function tandaiDibaca(Request $request, int $id): JsonResponse
    {
        $materi = Materi::findOrFail($id);
        $mahasiswaId = $request->user()->id;

        $progress = \App\Models\ProgressMateri::updateOrCreate(
            ['mahasiswa_id' => $mahasiswaId, 'materi_id' => $id],
            [
                'sudah_dibaca'       => true,
                'dibaca_pada'        => now(),
                'durasi_baca_detik'  => $request->durasi_detik ?? null,
            ]
        );

        // Update progress modul
        $this->updateProgressModulMahasiswa($materi->modul_id, $mahasiswaId);

        return response()->json([
            'message'  => 'Materi ditandai sudah dibaca',
            'progress' => $progress,
        ]);
    }

    // =============================================
    // PRIVATE HELPERS
    // =============================================

    /** Sinkronisasi jumlah_materi_total di semua progress modul ketika materi berubah */
    private function updateTotalMateriProgress(Modul $modul): void
    {
        $totalMateri = $modul->materis()->count();

        \App\Models\ProgressModul::where('modul_id', $modul->id)
            ->update(['jumlah_materi_total' => $totalMateri]);
    }

    /** Update progress modul mahasiswa setelah membaca materi */
    private function updateProgressModulMahasiswa(int $modulId, int $mahasiswaId): void
    {
        $modul    = Modul::find($modulId);
        $kelas    = $modul->kelas;

        $progress = \App\Models\ProgressModul::firstOrCreate(
            ['mahasiswa_id' => $mahasiswaId, 'modul_id' => $modulId],
            [
                'kelas_id'            => $kelas->id,
                'jumlah_materi_total' => $modul->materis()->count(),
            ]
        );

        // Hitung ulang materi selesai
        $selesai = \App\Models\ProgressMateri::where('mahasiswa_id', $mahasiswaId)
            ->whereIn('materi_id', $modul->materis()->pluck('id'))
            ->where('sudah_dibaca', true)
            ->count();

        $progress->update([
            'jumlah_materi_selesai' => $selesai,
            'jumlah_materi_total'   => $modul->materis()->count(),
        ]);

        $progress->hitungUlangPersentase();
    }
}
