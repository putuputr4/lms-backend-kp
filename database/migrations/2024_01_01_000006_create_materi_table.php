<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel materi: konten pembelajaran di dalam modul.
     * Tipe konten: pdf, video, slide, link, teks.
     */
    public function up(): void
    {
        Schema::create('materi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modul_id')->constrained('modul')->onDelete('cascade');
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->enum('tipe', ['pdf', 'video', 'slide', 'link', 'teks'])
                ->comment('Jenis konten materi');
            $table->string('file_path')->nullable()->comment('Path file untuk pdf/slide');
            $table->string('url')->nullable()->comment('URL untuk video/link eksternal');
            $table->text('konten_teks')->nullable()->comment('Konten teks langsung jika tipe=teks');
            $table->integer('urutan')->default(1);
            $table->integer('durasi_menit')->nullable()->comment('Estimasi waktu baca/tonton');
            $table->boolean('is_active')->default(true);
            $table->bigInteger('ukuran_file')->nullable()->comment('Ukuran file dalam bytes');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['modul_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materi');
    }
};
