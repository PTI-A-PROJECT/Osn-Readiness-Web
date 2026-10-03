<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aturan_pemetaan', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tingkat_id')->unique()->constrained('tingkat_seleksi')->restrictOnDelete();

            // Bobot & persentase pretest
            $table->unsignedSmallInteger('bobot_pretest');
            $table->unsignedSmallInteger('persen_pretest_mudah');
            $table->unsignedSmallInteger('persen_pretest_sedang');
            $table->unsignedSmallInteger('persen_pretest_sulit');
            $table->unsignedSmallInteger('passing_grade_pretest');

            // Bobot & persentase simulasi
            $table->unsignedSmallInteger('bobot_simulasi');
            $table->unsignedSmallInteger('persen_simulasi_mudah');
            $table->unsignedSmallInteger('persen_simulasi_sedang');
            $table->unsignedSmallInteger('persen_simulasi_sulit');
            $table->unsignedSmallInteger('passing_grade_simulasi');

            // Aturan latihan, pretest, simulasi
            $table->unsignedSmallInteger('latihan_min_nilai');
            $table->unsignedSmallInteger('pretest_jumlah_soal');
            $table->unsignedSmallInteger('pretest_min_soal_per_materi');
            $table->unsignedSmallInteger('simulasi_maks_percobaan');
            $table->unsignedSmallInteger('jumlah_materi_wajib');

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aturan_pemetaan');
    }
};
