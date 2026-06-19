<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel users menyimpan semua pengguna sistem LMS.
     * Role: mahasiswa | dosen | admin | super_admin
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['mahasiswa', 'dosen', 'admin', 'super_admin'])->default('mahasiswa');
            $table->string('nim_nip')->nullable()->unique()->comment('NIM untuk mahasiswa, NIP untuk dosen/admin');
            $table->string('foto_profil')->nullable();
            $table->string('no_hp')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('role');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
