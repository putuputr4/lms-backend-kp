<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel progress_materi: tracking materi mana saja yang sudah dibaca mahasiswa.
     */
    public function up(): void
    {
        Schema::create('progress_materi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('materi_id')->constrained('materi')->onDelete('cascade');
            $table->boolean('sudah_dibaca')->default(false);
            $table->timestamp('dibaca_pada')->nullable();
            $table->integer('durasi_baca_detik')->nullable()->comment('Berapa lama mahasiswa membaca');
            $table->timestamps();

            $table->unique(['mahasiswa_id', 'materi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_materi');
    }
};
