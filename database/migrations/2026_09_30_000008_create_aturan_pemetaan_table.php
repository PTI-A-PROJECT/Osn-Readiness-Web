<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aturan_pemetaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tingkat_seleksi_id')->constrained('tingkat_seleksi')->onDelete('cascade');
            
            // Pre-test configuration
            $table->integer('pretest_jumlah_soal')->default(30);
            $table->integer('pretest_persen_level_mudah')->default(50);
            $table->integer('pretest_persen_level_sedang')->default(30);
            $table->integer('pretest_persen_level_sulit')->default(20);
            $table->integer('pretest_min_soal_per_materi')->default(2);
            
            // Simulation configuration
            $table->integer('simulasi_jumlah_soal')->default(30);
            $table->integer('simulasi_persen_level_mudah')->default(30);
            $table->integer('simulasi_persen_level_sedang')->default(40);
            $table->integer('simulasi_persen_level_sulit')->default(30);
            $table->integer('simulasi_maks_percobaan')->default(3);
            $table->integer('simulasi_durasi_menit')->default(120);
            
            // General rules
            $table->integer('bobot')->default(1);
            $table->integer('passing_grade')->default(70);
            $table->integer('jumlah_materi_wajib')->default(3);
            $table->integer('latihan_min_soal')->default(10);
            $table->integer('latihan_min_nilai')->default(70);
            
            $table->unique('tingkat_seleksi_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aturan_pemetaan');
    }
};
