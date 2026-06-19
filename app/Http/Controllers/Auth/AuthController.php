<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login pengguna - semua role menggunakan endpoint yang sama.
     * Response berisi token Sanctum dan data user + role.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Cek kredensial
        if (!$user || !Hash::check($request->password, $user->password)) {
            AuditLog::catat('login_gagal', 'Auth', "Percobaan login gagal untuk email: {$request->email}");

            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Cek apakah akun aktif
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda telah dinonaktifkan. Hubungi Admin.'],
            ]);
        }

        // Update last login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Hapus token lama (opsional: batas 1 device)
        $user->tokens()->delete();

        // Buat token baru dengan kemampuan sesuai role
        $abilities = $this->getAbilitiesByRole($user->role);
        $token = $user->createToken('lms-token', $abilities)->plainTextToken;

        // Catat audit log
        AuditLog::catat('login_berhasil', 'Auth', "User {$user->email} ({$user->role}) berhasil login");

        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => [
                'id'           => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email'        => $user->email,
                'role'         => $user->role,
                'nim_nip'      => $user->nim_nip,
                'foto_profil'  => $user->foto_profil ? asset('storage/' . $user->foto_profil) : null,
            ],
        ]);
    }

    /**
     * Logout - hapus semua token aktif.
     */
    public function logout(Request $request): JsonResponse
    {
        AuditLog::catat('logout', 'Auth', "User {$request->user()->email} logout");

        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    /**
     * Registrasi mahasiswa baru (self-register).
     * Dosen/Admin hanya bisa dibuat oleh Super Admin.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => ['required', 'confirmed', Password::min(8)],
            'nim'          => 'required|string|max:20|unique:users,nim_nip',
            'no_hp'        => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'nama_lengkap' => $request->nama_lengkap,
            'email'        => $request->email,
            'password'     => $request->password,
            'role'         => 'mahasiswa',
            'nim_nip'      => $request->nim,
            'no_hp'        => $request->no_hp,
        ]);

        AuditLog::catat('register', 'Auth', "Mahasiswa baru terdaftar: {$user->email}");

        $token = $user->createToken('lms-token', $this->getAbilitiesByRole('mahasiswa'))->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'token'   => $token,
            'user'    => [
                'id'           => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email'        => $user->email,
                'role'         => $user->role,
                'nim_nip'      => $user->nim_nip,
            ],
        ], 201);
    }

    /**
     * Profil user yang sedang login.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'kelasIkuti:id,nama_kelas,kode_kelas',
        ]);

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update profil sendiri.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'nama_lengkap' => 'sometimes|string|max:255',
            'no_hp'        => 'sometimes|nullable|string|max:20',
            'foto_profil'  => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only(['nama_lengkap', 'no_hp']);

        if ($request->hasFile('foto_profil')) {
            $path = $request->file('foto_profil')->store('foto-profil', 'public');
            $data['foto_profil'] = $path;
        }

        $dataLama = $user->only(['nama_lengkap', 'no_hp']);
        $user->update($data);

        AuditLog::catat('update_profil', 'Auth', "User {$user->email} update profil", $dataLama, $data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * Kembalikan daftar abilities berdasarkan role.
     */
    private function getAbilitiesByRole(string $role): array
    {
        return match ($role) {
            'super_admin' => ['*'],
            'admin'       => ['admin:*', 'kelas:read', 'user:*', 'audit:read'],
            'dosen'       => ['kelas:*', 'tugas:*', 'nilai:*', 'materi:*'],
            'mahasiswa'   => ['kelas:read', 'tugas:read', 'tugas:submit', 'materi:read'],
            default       => [],
        };
    }
}
