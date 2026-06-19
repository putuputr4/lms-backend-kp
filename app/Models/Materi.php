<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Materi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'materi';

    protected $fillable = [
        'modul_id',
        'judul',
        'deskripsi',
        'tipe',
        'file_path',
        'url',
        'konten_teks',
        'urutan',
        'durasi_menit',
        'is_active',
        'ukuran_file',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'ukuran_file' => 'integer',
    ];

    public function modul()
    {
        return $this->belongsTo(Modul::class, 'modul_id');
    }

    public function progressMahasiswas()
    {
        return $this->hasMany(ProgressMateri::class, 'materi_id');
    }

    /** URL lengkap file jika ada */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return asset('storage/' . $this->file_path);
        }
        return $this->url;
    }

    /** Format ukuran file ke KB/MB */
    public function getUkuranFormatAttribute(): string
    {
        if (!$this->ukuran_file) return '-';
        if ($this->ukuran_file < 1024 * 1024) {
            return round($this->ukuran_file / 1024, 1) . ' KB';
        }
        return round($this->ukuran_file / (1024 * 1024), 1) . ' MB';
    }
}
