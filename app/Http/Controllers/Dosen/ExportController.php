<?php

namespace App\Http\Controllers\Dosen;

use App\Exports\NilaiExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Kelas;
use App\Models\ProgressModul;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    /**
     * Export laporan nilai kelas ke format Excel (.xlsx).
     */
    public function exportExcel(Request $request, int $kelasId)
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)
            ->with('moduls')
            ->findOrFail($kelasId);

        AuditLog::catat(
            'export_excel',
            'Export',
            "Export nilai kelas '{$kelas->nama_kelas}' ke Excel oleh {$request->user()->email}"
        );

        $filename = "laporan-nilai-{$kelas->kode_kelas}-" . now()->format('Ymd') . ".xlsx";

        return Excel::download(new NilaiExport($kelas), $filename);
    }

    /**
     * Export laporan nilai kelas ke format PDF.
     */
    public function exportPdf(Request $request, int $kelasId)
    {
        $kelas = Kelas::where('dosen_id', $request->user()->id)
            ->with([
                'dosen:id,nama_lengkap',
                'moduls',
                'mahasiswas' => function ($q) {
                    $q->withPivot(['nilai_akhir', 'status', 'tanggal_bergabung']);
                },
            ])
            ->findOrFail($kelasId);

        // Siapkan data progress per mahasiswa per modul
        $dataMahasiswa = $kelas->mahasiswas->map(function ($mahasiswa) use ($kelas) {
            $progressPerModul = collect();

            foreach ($kelas->moduls as $modul) {
                $progress = ProgressModul::where('mahasiswa_id', $mahasiswa->id)
                    ->where('modul_id', $modul->id)
                    ->first();

                $progressPerModul->push([
                    'judul_modul' => $modul->judul,
                    'persentase'  => $progress?->persentase ?? 0,
                    'nilai_tugas' => $progress?->nilai_tugas ?? '-',
                    'status'      => $progress?->status ?? 'belum_mulai',
                ]);
            }

            return [
                'nama'          => $mahasiswa->nama_lengkap,
                'nim'           => $mahasiswa->nim_nip ?? '-',
                'email'         => $mahasiswa->email,
                'nilai_akhir'   => $mahasiswa->pivot->nilai_akhir ?? '-',
                'status_kelas'  => $mahasiswa->pivot->status ?? '-',
                'progress_modul'=> $progressPerModul,
            ];
        });

        $statistik = [
            'total_mahasiswa' => $kelas->mahasiswas->count(),
            'rata_rata'       => round($kelas->mahasiswas->avg('pivot.nilai_akhir'), 2),
            'tertinggi'       => $kelas->mahasiswas->max('pivot.nilai_akhir'),
            'terendah'        => $kelas->mahasiswas->min('pivot.nilai_akhir'),
            'lulus'           => $kelas->mahasiswas->where('pivot.nilai_akhir', '>=', 70)->count(),
        ];

        $pdf = Pdf::loadView('exports.laporan-nilai', [
            'kelas'          => $kelas,
            'data_mahasiswa' => $dataMahasiswa,
            'statistik'      => $statistik,
            'tanggal_cetak'  => now()->format('d F Y H:i'),
        ])->setPaper('a4', 'landscape');

        AuditLog::catat(
            'export_pdf',
            'Export',
            "Export nilai kelas '{$kelas->nama_kelas}' ke PDF oleh {$request->user()->email}"
        );

        $filename = "laporan-nilai-{$kelas->kode_kelas}-" . now()->format('Ymd') . ".pdf";

        return $pdf->download($filename);
    }
}
