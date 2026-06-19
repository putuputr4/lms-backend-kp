<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PengumpulanTugas;
use App\Models\ProgressModul;
use App\Models\Tugas;
use App\Models\Modul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TugasController extends Controller
{
    /**
     * Buat tugas baru di dalam modul.
     */
    public function store(Request $request, int $modulId): JsonResponse
    {
        $modul = Modul::whereHas('kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->findOrFail($modulId);

        $request->validate([
            'judul'                    => 'required|string|max:255',
            'deskripsi'                => 'required|string',
            'instruksi'                => 'nullable|string',
            'tipe'                     => 'required|in:tugas,project',
            'batas_waktu'              => 'nullable|date|after:now',
            'bobot_nilai'              => 'nullable|integer|min:1|max:100',
            'kkm'                      => 'nullable|numeric|min:0|max:100',
            'wajib_upload_laporan'     => 'nullable|boolean',
            'wajib_upload_source_code' => 'nullable|boolean',
        ]);

        $tugas = Tugas::create([
            ...$request->only([
                'judul', 'deskripsi', 'instruksi', 'tipe',
                'batas_waktu', 'bobot_nilai', 'kkm',
                'wajib_upload_laporan', 'wajib_upload_source_code',
            ]),
            'modul_id' => $modulId,
            'dosen_id' => $request->user()->id,
        ]);

        AuditLog::catat('create_tugas', 'Tugas', "Buat tugas '{$tugas->judul}' di modul ID:{$modulId}");

        return response()->json([
            'message' => 'Tugas berhasil dibuat',
            'tugas'   => $tugas,
        ], 201);
    }

    /**
     * Daftar semua pengumpulan tugas (untuk dosen mereview).
     */
    public function daftarPengumpulan(Request $request, int $tugasId): JsonResponse
    {
        $tugas = Tugas::whereHas('modul.kelas', fn($q) => $q->where('dosen_id', $request->user()->id))
            ->with('modul.kelas:id,nama_kelas')
            ->findOrFail($tugasId);

        $pengumpulans = PengumpulanTugas::where('tugas_id', $tugasId)
            ->with('mahasiswa:id,nama_lengkap,nim_nip,email')
            ->orderBy('dikumpulkan_pada', 'desc')
            ->get();

        $statistik = [
            'total_mahasiswa'    => $tugas->modul->kelas->mahasiswas()->count(),
            'sudah_mengumpulkan' => $pengumpulans->count(),
            'belum_mengumpulkan' => $tugas->modul->kelas->mahasiswas()->count() - $pengumpulans->count(),
            'sudah_disetujui'    => $pengumpulans->where('status', 'disetujui')->count(),
            'ditolak'            => $pengumpulans->where('status', 'ditolak')->count(),
        ];

        return response()->json([
            'tugas'        => $tugas,
            'pengumpulans' => $pengumpulans,
            'statistik'    => $statistik,
        ]);
    }

    /**
     * Verifikasi/nilai pengumpulan tugas mahasiswa.
     * Jika disetujui → otomatis update progress modul.
     */
    public function verifikasiPengumpulan(Request $request, int $pengumpulanId): JsonResponse
    {
        $pengumpulan = PengumpulanTugas::whereHas(
            'tugas.modul.kelas',
            fn($q) => $q->where('dosen_id', $request->user()->id)
        )->with('tugas')->findOrFail($pengumpulanId);

        $request->validate([
            'status'          => 'required|in:disetujui,ditolak,sedang_direview',
            'nilai'           => 'required_if:status,disetujui|nullable|numeric|min:0|max:100',
            'feedback_dosen'  => 'nullable|string|max:2000',
        ]);

        $dataLama = $pengumpulan->toArray();

        $pengumpulan->update([
            'status'          => $request->status,
            'nilai'           => $request->nilai,
            'feedback_dosen'  => $request->feedback_dosen,
            'direview_oleh'   => $request->user()->id,
            'direview_pada'   => now(),
        ]);

        // Jika disetujui → update progress modul mahasiswa
        if ($request->status === 'disetujui') {
            $this->updateProgressSetelahVerifikasi($pengumpulan);
        }

        AuditLog::catat(
            'verifikasi_tugas',
            'Tugas',
            "Verifikasi pengumpulan ID:{$pengumpulanId} → {$request->status}, nilai: {$request->nilai}",
            $dataLama,
            $pengumpulan->fresh()->toArray()
        );

        return response()->json([
            'message'      => 'Verifikasi berhasil disimpan',
            'pengumpulan'  => $pengumpulan->fresh()->load('mahasiswa:id,nama_lengkap,nim_nip'),
        ]);
    }

    // =============================================
    // PRIVATE HELPER
    // =============================================

    /**
     * Setelah dosen setujui tugas → update progress modul mahasiswa.
     * Ini adalah inti dari alur: verifikasi dosen → otomatis ubah status progres.
     */
    private function updateProgressSetelahVerifikasi(PengumpulanTugas $pengumpulan): void
    {
        $tugas = $pengumpulan->tugas;
        $modul = $tugas->modul;
        $mahasiswaId = $pengumpulan->mahasiswa_id;

        $progress = ProgressModul::firstOrCreate(
            ['mahasiswa_id' => $mahasiswaId, 'modul_id' => $modul->id],
            [
                'kelas_id'            => $modul->kelas_id,
                'jumlah_materi_total' => $modul->materis()->count(),
            ]
        );

        // Update status tugas di progress
        $lulus = $pengumpulan->nilai >= $tugas->kkm;
        $progress->update([
            'tugas_selesai' => $lulus,
            'nilai_tugas'   => $pengumpulan->nilai,
        ]);

        // Hitung ulang total persentase
        $progress->hitungUlangPersentase();

        // Jika modul ini selesai 100% → update nilai akhir kelas
        if ($progress->fresh()->persentase >= 100) {
            $this->cekDanUpdateNilaiAkhirKelas($mahasiswaId, $modul->kelas_id);
        }
    }

    /** Update nilai akhir kelas jika semua modul sudah selesai */
    private function cekDanUpdateNilaiAkhirKelas(int $mahasiswaId, int $kelasId): void
    {
        $kelas       = \App\Models\Kelas::find($kelasId);
        $totalModul  = $kelas->moduls()->count();
        $modulSelesai = ProgressModul::where('mahasiswa_id', $mahasiswaId)
            ->where('kelas_id', $kelasId)
            ->where('status', 'selesai')
            ->count();

        if ($modulSelesai >= $totalModul) {
            // Hitung rata-rata nilai tugas semua modul
            $rataRata = ProgressModul::where('mahasiswa_id', $mahasiswaId)
                ->where('kelas_id', $kelasId)
                ->avg('nilai_tugas');

            $kelas->mahasiswas()->updateExistingPivot($mahasiswaId, [
                'nilai_akhir' => round($rataRata, 2),
                'status'      => 'selesai',
            ]);
        }
    }
}
