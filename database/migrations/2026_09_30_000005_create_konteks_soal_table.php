<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konteks_soal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tingkat_seleksi_id')->constrained('tingkat_seleksi')->onDelete('cascade');
            $table->text('deskripsi');
            $table->unique(['tingkat_seleksi_id', 'deskripsi'], 'konteks_soal_tingkat_deskripsi_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konteks_soal');
    }
};
