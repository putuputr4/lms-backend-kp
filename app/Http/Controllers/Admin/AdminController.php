<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AdminController extends Controller
{
    /**
     * Daftar semua user dengan filter role.
     * Hanya Super Admin dan Admin yang bisa akses.
     */
    public function indexUser(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter berdasarkan role
        if ($request->role) {
            $query->where('role', $request->role);
        }

        // Filter aktif/nonaktif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Pencarian nama/email/NIM
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_lengkap', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('nim_nip', 'like', "%{$request->search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    /**
     * Buat user baru (Dosen, Admin, atau Super Admin).
     * Hanya Super Admin yang bisa membuat Super Admin lain.
     */
    public function createUser(Request $request): JsonResponse
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => ['required', Password::min(8)],
            'role'         => 'required|in:mahasiswa,dosen,admin,super_admin',
            'nim_nip'      => 'nullable|string|max:20|unique:users,nim_nip',
            'no_hp'        => 'nullable|string|max:20',
        ]);

        // Hanya Super Admin yang bisa buat Super Admin lain
        if ($request->role === 'super_admin' && !$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Tidak memiliki izin membuat Super Admin.'], 403);
        }

        $user = User::create([
            'nama_lengkap' => $request->nama_lengkap,
            'email'        => $request->email,
            'password'     => $request->password,
            'role'         => $request->role,
            'nim_nip'      => $request->nim_nip,
            'no_hp'        => $request->no_hp,
        ]);

        AuditLog::catat(
            'create_user',
            'User',
            "Buat user baru: {$user->email} (role: {$user->role})",
            null,
            $user->except('password')->toArray()
        );

        return response()->json([
            'message' => 'User berhasil dibuat',
            'user'    => $user,
        ], 201);
    }

    /**
     * Update data user (oleh Admin/Super Admin).
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Admin tidak bisa edit Super Admin
        if ($user->isSuperAdmin() && !$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Tidak memiliki izin mengubah Super Admin.'], 403);
        }

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'email'        => "sometimes|email|unique:users,email,{$id}",
            'role'         => 'sometimes|in:mahasiswa,dosen,admin,super_admin',
            'nim_nip'      => "sometimes|nullable|string|max:20|unique:users,nim_nip,{$id}",
            'no_hp'        => 'sometimes|nullable|string|max:20',
            'is_active'    => 'sometimes|boolean',
        ]);

        $dataLama = $user->toArray();
        $user->update($request->only(['nama_lengkap', 'email', 'role', 'nim_nip', 'no_hp', 'is_active']));

        AuditLog::catat(
            'update_user',
            'User',
            "Update user ID:{$id} ({$user->email})",
            $dataLama,
            $user->fresh()->toArray()
        );

        return response()->json([
            'message' => 'User berhasil diperbarui',
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * Nonaktifkan / aktifkan user (toggle is_active).
     */
    public function toggleAktifUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat menonaktifkan akun sendiri.'], 400);
        }

        $statusLama = $user->is_active;
        $user->update(['is_active' => !$user->is_active]);

        $statusBaru = $user->is_active ? 'aktif' : 'nonaktif';
        AuditLog::catat(
            'toggle_user',
            'User',
            "User {$user->email} diubah menjadi {$statusBaru}",
            ['is_active' => $statusLama],
            ['is_active' => $user->is_active]
        );

        return response()->json([
            'message'   => "User berhasil di{$statusBaru}kan",
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Reset password user (oleh Admin/Super Admin).
     */
    public function resetPasswordUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'password_baru' => ['required', Password::min(8), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->password_baru)]);

        AuditLog::catat(
            'reset_password',
            'User',
            "Reset password untuk user {$user->email}"
        );

        return response()->json(['message' => 'Password berhasil direset']);
    }

    /**
     * Audit Log - riwayat aktivitas seluruh pengguna.
     * Filter berdasarkan user, aksi, modul, atau rentang tanggal.
     */
    public function auditLog(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,nama_lengkap,email,role')
            ->orderBy('created_at', 'desc');

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->aksi) {
            $query->where('aksi', 'like', "%{$request->aksi}%");
        }

        if ($request->modul) {
            $query->where('modul', $request->modul);
        }

        if ($request->dari_tanggal) {
            $query->whereDate('created_at', '>=', $request->dari_tanggal);
        }

        if ($request->sampai_tanggal) {
            $query->whereDate('created_at', '<=', $request->sampai_tanggal);
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }

    /**
     * Statistik ringkasan untuk dashboard Super Admin.
     */
    public function statistikDashboard(): JsonResponse
    {
        return response()->json([
            'total_user' => [
                'mahasiswa'   => User::where('role', 'mahasiswa')->count(),
                'dosen'       => User::where('role', 'dosen')->count(),
                'admin'       => User::where('role', 'admin')->count(),
                'super_admin' => User::where('role', 'super_admin')->count(),
            ],
            'total_kelas'         => \App\Models\Kelas::count(),
            'total_aktif'         => User::where('is_active', true)->count(),
            'total_nonaktif'      => User::where('is_active', false)->count(),
            'login_hari_ini'      => AuditLog::where('aksi', 'login_berhasil')
                ->whereDate('created_at', today())
                ->count(),
            'aktivitas_terbaru'   => AuditLog::with('user:id,nama_lengkap,role')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ]);
    }
}
