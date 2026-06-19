<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressModul extends Model
{
    use HasFactory;

    protected $table = 'progress_modul';

    protected $fillable = [
        'mahasiswa_id',
        'kelas_id',
        'modul_id',
        'persentase',
        'status',
        'jumlah_materi_selesai',
        'jumlah_materi_total',
        'tugas_selesai',
        'nilai_tugas',
        'mulai_pada',
        'selesai_pada',
    ];

    protected $casts = [
        'persentase'  => 'decimal:2',
        'nilai_tugas' => 'decimal:2',
        'tugas_selesai' => 'boolean',
        'mulai_pada'  => 'datetime',
        'selesai_pada'=> 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function modul()
    {
        return $this->belongsTo(Modul::class, 'modul_id');
    }

    /**
     * Hitung ulang persentase progress berdasarkan materi dan tugas.
     * Dipanggil setiap kali ada update (materi dibaca / tugas diverifikasi).
     */
    public function hitungUlangPersentase(): void
    {
        $totalMateri = $this->jumlah_materi_total;
        $selesaiMateri = $this->jumlah_materi_selesai;
        $tugasSelesai = $this->tugas_selesai;

        if ($totalMateri === 0) {
            $persentaseMateri = $tugasSelesai ? 100 : 0;
        } else {
            // Materi = 70%, Tugas = 30% dari total progress
            $persentaseMateri = ($selesaiMateri / $totalMateri) * 70;
            $persentaseTugas  = $tugasSelesai ? 30 : 0;
            $persentaseMateri = $persentaseMateri + $persentaseTugas;
        }

        $this->persentase = min(100, round($persentaseMateri, 2));

        if ($this->persentase >= 100) {
            $this->status = 'selesai';
            $this->selesai_pada = $this->selesai_pada ?? now();
        } elseif ($this->persentase > 0) {
            $this->status = 'sedang_berjalan';
            $this->mulai_pada = $this->mulai_pada ?? now();
        }

        $this->save();
    }
}
