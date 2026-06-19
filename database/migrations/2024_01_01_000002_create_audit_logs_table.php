<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit log untuk mencatat seluruh aktivitas pengguna,
     * terutama aktivitas Super Admin dan Admin.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('user_email')->nullable()->comment('Disimpan terpisah agar tetap ada walau user dihapus');
            $table->string('role')->nullable()->comment('Role saat aksi dilakukan');
            $table->string('aksi')->comment('Contoh: login, create_kelas, delete_user, override_plagiarisme');
            $table->string('modul')->comment('Contoh: Auth, Kelas, Tugas, Plagiarisme');
            $table->text('deskripsi')->nullable()->comment('Detail lengkap aksi yang dilakukan');
            $table->json('data_lama')->nullable()->comment('State data sebelum diubah');
            $table->json('data_baru')->nullable()->comment('State data sesudah diubah');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('aksi');
            $table->index('modul');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
