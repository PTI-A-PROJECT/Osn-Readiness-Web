<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_jawaban', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_pengerjaan_id')->constrained('quiz_pengerjaan')->onDelete('cascade');
            $table->foreignId('soal_id')->constrained('soal')->onDelete('cascade');
            $table->string('jawaban_user', 1)->nullable();
            $table->boolean('status_benar')->nullable();
            $table->integer('urutan');
            $table->decimal('bobot', 8, 2);
            $table->timestamps();
            $table->unique(['quiz_pengerjaan_id', 'soal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_jawaban');
    }
};
