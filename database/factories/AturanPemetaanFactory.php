<?php

namespace Database\Factories;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AturanPemetaan>
 */
class AturanPemetaanFactory extends Factory
{
    protected $model = AturanPemetaan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tingkat_id' => TingkatSeleksi::factory(),
            'bobot_pretest' => fake()->numberBetween(20, 40),
            'persen_pretest_mudah' => fake()->numberBetween(30, 60),
            'persen_pretest_sedang' => fake()->numberBetween(20, 50),
            'persen_pretest_sulit' => fake()->numberBetween(10, 40),
            'passing_grade_pretest' => fake()->numberBetween(50, 70),
            'bobot_simulasi' => fake()->numberBetween(50, 70),
            'persen_simulasi_mudah' => fake()->numberBetween(20, 40),
            'persen_simulasi_sedang' => fake()->numberBetween(30, 50),
            'persen_simulasi_sulit' => fake()->numberBetween(10, 40),
            'passing_grade_simulasi' => fake()->numberBetween(60, 80),
            'latihan_min_nilai' => fake()->numberBetween(50, 70),
            'pretest_jumlah_soal' => 30,
            'pretest_min_soal_per_materi' => fake()->numberBetween(1, 3),
            'simulasi_maks_percobaan' => fake()->numberBetween(2, 5),
            'jumlah_materi_wajib' => fake()->numberBetween(3, 10),
        ];
    }
}
