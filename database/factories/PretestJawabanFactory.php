<?php

namespace Database\Factories;

use App\Models\Pretest;
use App\Models\PretestJawaban;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PretestJawaban>
 */
class PretestJawabanFactory extends Factory
{
    protected $model = PretestJawaban::class;

    public function definition(): array
    {
        return [
            'pretest_id' => Pretest::factory(),
            'soal_id' => Soal::factory(),
            'jawaban_user' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'status_benar' => $this->faker->boolean(),
            'urutan' => $this->faker->numberBetween(1, 30),
            'bobot' => 1,
        ];
    }
}
