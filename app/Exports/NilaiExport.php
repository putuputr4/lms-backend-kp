<?php

namespace App\Exports;

use App\Models\Kelas;
use App\Models\ProgressModul;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class NilaiExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithTitle,
    ShouldAutoSize
{
    protected Kelas $kelas;

    public function __construct(Kelas $kelas)
    {
        $this->kelas = $kelas;
    }

    public function title(): string
    {
        return 'Laporan Nilai';
    }

    /**
     * Data yang akan diexport: semua mahasiswa + nilai per modul.
     */
    public function collection()
    {
        return $this->kelas->mahasiswas()
            ->withPivot(['nilai_akhir', 'status', 'tanggal_bergabung'])
            ->get();
    }

    /**
     * Header kolom Excel.
     */
    public function headings(): array
    {
        $headers = ['No', 'NIM', 'Nama Mahasiswa', 'Email'];

        // Tambah kolom per modul
        foreach ($this->kelas->moduls as $index => $modul) {
            $headers[] = "Modul " . ($index + 1) . ": {$modul->judul}";
        }

        $headers[] = 'Nilai Akhir';
        $headers[] = 'Status';
        $headers[] = 'Tanggal Bergabung';

        return $headers;
    }

    /**
     * Mapping data per baris.
     */
    public function map($mahasiswa): array
    {
        static $no = 0;
        $no++;

        $row = [
            $no,
            $mahasiswa->nim_nip ?? '-',
            $mahasiswa->nama_lengkap,
            $mahasiswa->email,
        ];

        // Nilai per modul
        foreach ($this->kelas->moduls as $modul) {
            $progress = ProgressModul::where('mahasiswa_id', $mahasiswa->id)
                ->where('modul_id', $modul->id)
                ->first();

            if ($progress) {
                $row[] = $progress->nilai_tugas
                    ? "{$progress->nilai_tugas} ({$progress->status})"
                    : "-";
            } else {
                $row[] = 'Belum mulai';
            }
        }

        $row[] = $mahasiswa->pivot->nilai_akhir ?? '-';
        $row[] = ucfirst($mahasiswa->pivot->status ?? '-');
        $row[] = $mahasiswa->pivot->tanggal_bergabung
            ? \Carbon\Carbon::parse($mahasiswa->pivot->tanggal_bergabung)->format('d/m/Y')
            : '-';

        return $row;
    }

    /**
     * Styling Excel.
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            // Header row: background biru, teks putih, bold
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E40AF'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
