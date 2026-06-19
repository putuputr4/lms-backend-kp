<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pengumpulan_tugas: submission mahasiswa untuk setiap tugas.
     * Menyimpan file laporan, source code, dan status verifikasi dosen.
     */
    public function up(): void
    {
        Schema::create('pengumpulan_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_id')->constrained('tugas')->onDelete('cascade');
            $table->foreignId('mahasiswa_id')->constrained('users')->onDelete('cascade');
            $table->string('file_laporan')->nullable()->comment('Path file laporan (PDF/Word)');
            $table->string('file_source_code')->nullable()->comment('Path file source code (zip)');
            $table->text('catatan_mahasiswa')->nullable()->comment('Catatan dari mahasiswa');
            $table->enum('status', [
                'belum_dikumpulkan',
                'terkumpul',
                'sedang_direview',
                'disetujui',
                'ditolak',
                'plagiat_terdeteksi',
                'dikunci'
            ])->default('terkumpul');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('feedback_dosen')->nullable();
            $table->foreignId('direview_oleh')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('direview_pada')->nullable();
            $table->timestamp('dikumpulkan_pada')->useCurrent();
            $table->boolean('terlambat')->default(false);
            $table->integer('percobaan_ke')->default(1)->comment('Urutan pengumpulan (jika ada revisi)');
            $table->timestamps();

            $table->index(['tugas_id', 'mahasiswa_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumpulan_tugas');
    }
};
