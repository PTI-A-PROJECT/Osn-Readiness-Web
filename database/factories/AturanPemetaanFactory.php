<?php

namespace Database\Factories;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AturanPemetaan>
 */
class AturanPemetaanFactory extends Factory
{
    protected $model = AturanPemetaan::class;

    public function definition(): array
    {
        return [
            'tingkat_seleksi_id' => TingkatSeleksi::factory(),
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
        ];
    }
}
