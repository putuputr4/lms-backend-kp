<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel progress: tracking kemajuan mahasiswa per modul dan materi.
     * Diupdate otomatis saat dosen verifikasi tugas.
     */
    public function up(): void
    {
        Schema::create('progress_modul', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            $table->foreignId('modul_id')->constrained('modul')->onDelete('cascade');
            $table->decimal('persentase', 5, 2)->default(0.00)
                ->comment('0.00 - 100.00 persen penyelesaian modul');
            $table->enum('status', ['belum_mulai', 'sedang_berjalan', 'selesai', 'dikunci'])
                ->default('belum_mulai');
            $table->integer('jumlah_materi_selesai')->default(0);
            $table->integer('jumlah_materi_total')->default(0);
            $table->boolean('tugas_selesai')->default(false);
            $table->decimal('nilai_tugas', 5, 2)->nullable();
            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'modul_id']);
            $table->index(['mahasiswa_id', 'kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_modul');
    }
};
