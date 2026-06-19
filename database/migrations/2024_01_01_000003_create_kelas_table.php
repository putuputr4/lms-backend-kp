<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel kelas: kelas online yang dibuat oleh Dosen.
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_kelas');
            $table->string('kode_kelas')->unique()->comment('Kode unik untuk join kelas, contoh: CS-2024-001');
            $table->text('deskripsi')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('semester')->nullable()->comment('Contoh: Ganjil 2025/2026');
            $table->string('tahun_ajaran')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('tanggal_mulai')->nullable();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('dosen_id');
            $table->index('kode_kelas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
