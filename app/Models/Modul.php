<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modul extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modul';

    protected $fillable = [
        'kelas_id',
        'judul',
        'deskripsi',
        'urutan',
        'is_active',
        'wajib_selesai_sebelumnya',
    ];

    protected $casts = [
        'is_active'                 => 'boolean',
        'wajib_selesai_sebelumnya'  => 'boolean',
    ];

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function materis()
    {
        return $this->hasMany(Materi::class, 'modul_id')->orderBy('urutan');
    }

    public function tugass()
    {
        return $this->hasMany(Tugas::class, 'modul_id');
    }

    public function progressMahasiswas()
    {
        return $this->hasMany(ProgressModul::class, 'modul_id');
    }

    /** Modul sebelumnya (untuk cek unlock) */
    public function modulSebelumnya()
    {
        return Modul::where('kelas_id', $this->kelas_id)
                    ->where('urutan', $this->urutan - 1)
                    ->first();
    }
}
