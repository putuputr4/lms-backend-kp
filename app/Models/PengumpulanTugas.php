<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengumpulanTugas extends Model
{
    use HasFactory;

    protected $table = 'pengumpulan_tugas';

    protected $fillable = [
        'tugas_id',
        'mahasiswa_id',
        'file_laporan',
        'file_source_code',
        'catatan_mahasiswa',
        'status',
        'nilai',
        'feedback_dosen',
        'direview_oleh',
        'direview_pada',
        'dikumpulkan_pada',
        'terlambat',
        'percobaan_ke',
    ];

    protected $casts = [
        'nilai'           => 'decimal:2',
        'terlambat'       => 'boolean',
        'direview_pada'   => 'datetime',
        'dikumpulkan_pada'=> 'datetime',
    ];

    public function tugas()
    {
        return $this->belongsTo(Tugas::class, 'tugas_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function reviewerDosen()
    {
        return $this->belongsTo(User::class, 'direview_oleh');
    }

    /** URL file laporan */
    public function getLaporanUrlAttribute(): ?string
    {
        return $this->file_laporan ? asset('storage/' . $this->file_laporan) : null;
    }

    /** URL file source code */
    public function getSourceCodeUrlAttribute(): ?string
    {
        return $this->file_source_code ? asset('storage/' . $this->file_source_code) : null;
    }

    /** Apakah sudah lulus KKM */
    public function getLulusAttribute(): bool
    {
        return $this->nilai !== null && $this->nilai >= $this->tugas->kkm;
    }
}
