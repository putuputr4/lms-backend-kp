<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgressMateri extends Model
{
    use HasFactory;

    protected $table = 'progress_materi';

    protected $fillable = [
        'mahasiswa_id',
        'materi_id',
        'sudah_dibaca',
        'dibaca_pada',
        'durasi_baca_detik',
    ];

    protected $casts = [
        'sudah_dibaca' => 'boolean',
        'dibaca_pada'  => 'datetime',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'materi_id');
    }
}
