<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Kelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'kelas';

    protected $fillable = [
        'dosen_id',
        'nama_kelas',
        'kode_kelas',
        'deskripsi',
        'thumbnail',
        'semester',
        'tahun_ajaran',
        'is_active',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'tanggal_mulai'   => 'datetime',
        'tanggal_selesai' => 'datetime',
    ];

    // =============================================
    // BOOT: Auto-generate kode kelas
    // =============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($kelas) {
            if (empty($kelas->kode_kelas)) {
                $kelas->kode_kelas = strtoupper(Str::random(3)) . '-' . date('Y') . '-' . str_pad(
                    Kelas::withTrashed()->count() + 1,
                    3, '0', STR_PAD_LEFT
                );
            }
        });
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function dosen()
    {
        return $this->belongsTo(User::class, 'dosen_id');
    }

    public function mahasiswas()
    {
        return $this->belongsToMany(User::class, 'kelas_mahasiswa', 'kelas_id', 'mahasiswa_id')
                    ->withPivot(['status', 'nilai_akhir', 'tanggal_bergabung'])
                    ->withTimestamps();
    }

    public function moduls()
    {
        return $this->hasMany(Modul::class, 'kelas_id')->orderBy('urutan');
    }

    public function progressModuls()
    {
        return $this->hasMany(ProgressModul::class, 'kelas_id');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    /** Hitung total materi dari semua modul */
    public function getTotalMateriAttribute(): int
    {
        return $this->moduls->sum(fn($m) => $m->materis->count());
    }

    /** Hitung jumlah mahasiswa aktif */
    public function getJumlahMahasiswaAktifAttribute(): int
    {
        return $this->mahasiswas()->wherePivot('status', 'aktif')->count();
    }
}
