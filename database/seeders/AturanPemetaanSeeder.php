<?php

namespace Database\Seeders;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

class AturanPemetaanSeeder extends Seeder
{
    public function run(): void
    {
        $tingkats = TingkatSeleksi::all();

        foreach ($tingkats as $tingkat) {
            AturanPemetaan::firstOrCreate(
                ['tingkat_seleksi_id' => $tingkat->id],
                [
                    'pretest_jumlah_soal' => 30,
                    'pretest_persen_level_mudah' => 50,
                    'pretest_persen_level_sedang' => 30,
                    'pretest_persen_level_sulit' => 20,
                    'pretest_min_soal_per_materi' => 2,
                    'simulasi_jumlah_soal' => 30,
                    'simulasi_persen_level_mudah' => 30,
                    'simulasi_persen_level_sedang' => 40,
                    'simulasi_persen_level_sulit' => 30,
                    'simulasi_maks_percobaan' => 3,
                    'simulasi_durasi_menit' => 120,
                    'bobot' => 1,
                    'passing_grade' => 70,
                    'jumlah_materi_wajib' => 3,
                    'latihan_min_soal' => 10,
                    'latihan_min_nilai' => 70,
                ]
            );
        }
    }
}
