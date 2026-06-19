<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel modul: modul/bab dalam sebuah kelas.
     * Setiap kelas bisa punya banyak modul berurutan.
     */
    public function up(): void
    {
        Schema::create('modul', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            $table->string('judul');
            $table->text('deskripsi')->nullable();
            $table->integer('urutan')->default(1)->comment('Urutan modul dalam kelas');
            $table->boolean('is_active')->default(true);
            $table->boolean('wajib_selesai_sebelumnya')->default(true)
                ->comment('Jika true, mahasiswa harus selesaikan modul sebelumnya dulu');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kelas_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modul');
    }
};
