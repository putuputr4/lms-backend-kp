<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Dosen\ExportController;
use App\Http\Controllers\Dosen\KelasController;
use App\Http\Controllers\Dosen\MateriController;
use App\Http\Controllers\Dosen\TugasController;
use App\Http\Controllers\Mahasiswa\MahasiswaTugasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - LMS Backend Core (Mahasiswa 2)
|--------------------------------------------------------------------------
|
| Semua endpoint menggunakan prefix /api/v1
| Autentikasi: Laravel Sanctum (Bearer Token)
|
*/

Route::prefix('v1')->group(function () {

    // =============================================
    // PUBLIC ROUTES (Tanpa autentikasi)
    // =============================================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']); // Khusus mahasiswa baru
    });

    // =============================================
    // AUTHENTICATED ROUTES (Semua role yang login)
    // =============================================
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::post('/profile', [AuthController::class, 'updateProfile']);
        });

        // ==========================================
        // MAHASISWA ROUTES
        // ==========================================
        Route::middleware('role:mahasiswa')->prefix('mahasiswa')->group(function () {

            // Dashboard mahasiswa (semua kelas + progress)
            Route::get('/dashboard', [MahasiswaTugasController::class, 'dashboard']);

            // Submit tugas
            Route::post('/tugas/{tugasId}/submit', [MahasiswaTugasController::class, 'submit']);

            // Riwayat pengumpulan tugas
            Route::get('/tugas/{tugasId}/riwayat', [MahasiswaTugasController::class, 'riwayat']);

            // Tandai materi sudah dibaca
            Route::post('/materi/{id}/baca', [MateriController::class, 'tandaiDibaca']);
        });

        // ==========================================
        // DOSEN ROUTES
        // ==========================================
        Route::middleware('role:dosen')->prefix('dosen')->group(function () {

            // Manajemen Kelas
            Route::apiResource('kelas', KelasController::class);
            Route::post('kelas/{id}/mahasiswa', [KelasController::class, 'daftarkanMahasiswa']);
            Route::get('kelas/{id}/statistik', [KelasController::class, 'statistikNilai']);

            // Manajemen Modul (buat, update, hapus modul dalam kelas)
            Route::apiResource('kelas/{kelasId}/modul', \App\Http\Controllers\Dosen\ModulController::class);

            // Manajemen Materi (upload PDF, video, slide)
            Route::post('modul/{modulId}/materi', [MateriController::class, 'store']);
            Route::put('materi/{id}', [MateriController::class, 'update']);
            Route::delete('materi/{id}', [MateriController::class, 'destroy']);

            // Manajemen Tugas
            Route::post('modul/{modulId}/tugas', [TugasController::class, 'store']);
            Route::get('tugas/{tugasId}/pengumpulan', [TugasController::class, 'daftarPengumpulan']);
            Route::post('pengumpulan/{id}/verifikasi', [TugasController::class, 'verifikasiPengumpulan']);

            // Export Laporan
            Route::get('kelas/{kelasId}/export/excel', [ExportController::class, 'exportExcel']);
            Route::get('kelas/{kelasId}/export/pdf', [ExportController::class, 'exportPdf']);
        });

        // ==========================================
        // ADMIN ROUTES
        // ==========================================
        Route::middleware('role:admin,super_admin')->prefix('admin')->group(function () {

            // Manajemen User
            Route::get('/users', [AdminController::class, 'indexUser']);
            Route::post('/users', [AdminController::class, 'createUser']);
            Route::put('/users/{id}', [AdminController::class, 'updateUser']);
            Route::patch('/users/{id}/toggle-aktif', [AdminController::class, 'toggleAktifUser']);
            Route::post('/users/{id}/reset-password', [AdminController::class, 'resetPasswordUser']);

            // Audit Log
            Route::get('/audit-log', [AdminController::class, 'auditLog']);

            // Dashboard Super Admin
            Route::get('/dashboard', [AdminController::class, 'statistikDashboard']);
        });
    });
});
