<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    // Audit log tidak boleh diubah setelah dibuat
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_email',
        'role',
        'aksi',
        'modul',
        'deskripsi',
        'data_lama',
        'data_baru',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'data_lama' => 'array',
        'data_baru' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Helper statis untuk mencatat log dengan mudah.
     *
     * @param string $aksi    Nama aksi, misal: 'create_kelas', 'delete_user'
     * @param string $modul   Nama modul, misal: 'Kelas', 'User', 'Tugas'
     * @param string $deskripsi Keterangan detail
     * @param array|null $dataLama Data sebelum perubahan
     * @param array|null $dataBaru Data setelah perubahan
     */
    public static function catat(
        string $aksi,
        string $modul,
        string $deskripsi,
        ?array $dataLama = null,
        ?array $dataBaru = null
    ): void {
        $user = auth()->user();

        static::create([
            'user_id'    => $user?->id,
            'user_email' => $user?->email,
            'role'       => $user?->role,
            'aksi'       => $aksi,
            'modul'      => $modul,
            'deskripsi'  => $deskripsi,
            'data_lama'  => $dataLama,
            'data_baru'  => $dataBaru,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
