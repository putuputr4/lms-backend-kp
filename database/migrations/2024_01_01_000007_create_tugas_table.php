<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel tugas: assignment/project yang diberikan Dosen dalam modul.
     */
    public function up(): void
    {
        Schema::create('tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modul_id')->constrained('modul')->onDelete('cascade');
            $table->foreignId('dosen_id')->constrained('users')->onDelete('cascade');
            $table->string('judul');
            $table->text('deskripsi');
            $table->text('instruksi')->nullable()->comment('Instruksi detail pengerjaan');
            $table->enum('tipe', ['tugas', 'project'])->default('tugas');
            $table->timestamp('batas_waktu')->nullable()->comment('Deadline pengumpulan');
            $table->integer('bobot_nilai')->default(100)->comment('Bobot nilai maksimal');
            $table->decimal('kkm', 5, 2)->default(70.00)->comment('Nilai Ketuntasan Minimal');
            $table->boolean('wajib_upload_laporan')->default(true);
            $table->boolean('wajib_upload_source_code')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('modul_id');
            $table->index('dosen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas');
    }
};
