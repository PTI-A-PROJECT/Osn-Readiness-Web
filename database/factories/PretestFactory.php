<?php

namespace Database\Factories;

use App\Models\Pretest;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pretest>
 */
class PretestFactory extends Factory
{
    protected $model = Pretest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tingkat_seleksi_id' => TingkatSeleksi::factory(),
            'nilai' => $this->faker->numberBetween(0, 100),
            'disubmit_pada' => $this->faker->dateTime(),
            'selesai_pada' => $this->faker->dateTime(),
        ];
    }

    public function berjalan(): static
    {
        return $this->state(fn (array $attributes) => [
            'nilai' => null,
            'disubmit_pada' => null,
            'selesai_pada' => null,
        ]);
    }
}
