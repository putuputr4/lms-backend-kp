<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama_lengkap',
        'email',
        'password',
        'role',
        'nim_nip',
        'foto_profil',
        'no_hp',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // =============================================
    // ROLE HELPER METHODS
    // =============================================

    public function isMahasiswa(): bool
    {
        return $this->role === 'mahasiswa';
    }

    public function isDosen(): bool
    {
        return $this->role === 'dosen';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdminOrSuperAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /** Kelas yang diajar oleh dosen ini */
    public function kelasDiajar()
    {
        return $this->hasMany(Kelas::class, 'dosen_id');
    }

    /** Kelas yang diikuti mahasiswa (via pivot) */
    public function kelasIkuti()
    {
        return $this->belongsToMany(Kelas::class, 'kelas_mahasiswa', 'mahasiswa_id', 'kelas_id')
                    ->withPivot(['status', 'nilai_akhir', 'tanggal_bergabung'])
                    ->withTimestamps();
    }

    /** Progress semua modul mahasiswa */
    public function progressModul()
    {
        return $this->hasMany(ProgressModul::class, 'mahasiswa_id');
    }

    /** Semua pengumpulan tugas mahasiswa */
    public function pengumpulanTugas()
    {
        return $this->hasMany(PengumpulanTugas::class, 'mahasiswa_id');
    }

    /** Tugas yang dibuat dosen */
    public function tugasDibuat()
    {
        return $this->hasMany(Tugas::class, 'dosen_id');
    }

    /** Audit log milik user ini */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
