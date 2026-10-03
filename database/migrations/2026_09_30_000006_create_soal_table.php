<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materi_id')->constrained('materi')->onDelete('cascade');
            $table->foreignId('konteks_soal_id')->nullable()->constrained('konteks_soal')->nullOnDelete();
            $table->text('pertanyaan');
            $table->json('pilihan')->nullable(); // null untuk soal isian
            $table->char('jawaban_benar', 1)->nullable();
            $table->string('level', 20); // mudah, sedang, sulit
            $table->string('peruntukan', 20); // pretest, latihan, simulasi
            $table->string('tipe_soal', 20); // pilihan_ganda, isian
            $table->string('gambar')->nullable();
            $table->string('id_sumber')->nullable();
            $table->softDeletes();
            $table->unique('id_sumber');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal');
    }
};
