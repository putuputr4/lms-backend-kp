<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pivot: enrollmen mahasiswa ke kelas.
     */
    public function up(): void
    {
        Schema::create('kelas_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            $table->foreignId('mahasiswa_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['aktif', 'selesai', 'dropout'])->default('aktif');
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->timestamp('tanggal_bergabung')->useCurrent();
            $table->timestamps();

            $table->unique(['kelas_id', 'mahasiswa_id']);
            $table->index('mahasiswa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_mahasiswa');
    }
};
