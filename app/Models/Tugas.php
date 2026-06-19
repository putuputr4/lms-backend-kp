<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tugas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tugas';

    protected $fillable = [
        'modul_id',
        'dosen_id',
        'judul',
        'deskripsi',
        'instruksi',
        'tipe',
        'batas_waktu',
        'bobot_nilai',
        'kkm',
        'wajib_upload_laporan',
        'wajib_upload_source_code',
        'is_active',
    ];

    protected $casts = [
        'batas_waktu'              => 'datetime',
        'bobot_nilai'              => 'integer',
        'kkm'                      => 'decimal:2',
        'wajib_upload_laporan'     => 'boolean',
        'wajib_upload_source_code' => 'boolean',
        'is_active'                => 'boolean',
    ];

    public function modul()
    {
        return $this->belongsTo(Modul::class, 'modul_id');
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function pengumpulans()
    {
        return $this->hasMany(PengumpulanTugas::class, 'tugas_id');
    }

    /** Apakah deadline sudah lewat */
    public function getTerlambatAttribute(): bool
    {
        return $this->batas_waktu && now()->gt($this->batas_waktu);
    }
}
