<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Middleware pengecekan role pengguna.
     *
     * Cara pakai di routes:
     *   ->middleware('role:dosen')
     *   ->middleware('role:admin,super_admin')
     *
     * @param string $roles Satu atau lebih role yang diizinkan, dipisah koma
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Pastikan sudah login
        if (!$request->user()) {
            return response()->json([
                'message' => 'Silakan login terlebih dahulu.',
            ], 401);
        }

        // Cek apakah role user ada di daftar role yang diizinkan
        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'message' => 'Anda tidak memiliki izin untuk mengakses fitur ini.',
                'role_anda'   => $request->user()->role,
                'role_dibutuhkan' => $roles,
            ], 403);
        }

        // Cek apakah akun masih aktif
        if (!$request->user()->is_active) {
            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi Admin.',
            ], 403);
        }

        return $next($request);
    }
}
