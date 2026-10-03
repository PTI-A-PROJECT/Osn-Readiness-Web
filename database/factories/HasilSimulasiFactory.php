<?php

namespace Database\Factories;

use App\Models\HasilSimulasi;
use App\Models\Pretest;
use App\Models\Simulasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HasilSimulasi>
 */
class HasilSimulasiFactory extends Factory
{
    protected $model = HasilSimulasi::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'simulasi_id' => Simulasi::factory(),
            'pretest_id' => Pretest::factory(),
            'nilai' => $this->faker->numberBetween(0, 100),
            'jumlah_benar' => $this->faker->numberBetween(0, 30),
            'jumlah_salah' => $this->faker->numberBetween(0, 30),
            'lulus' => $this->faker->boolean(),
            'mulai_pada' => $this->faker->dateTime(),
            'batas_pada' => $this->faker->dateTime(),
            'disubmit_pada' => $this->faker->dateTime(),
            'selesai_pada' => $this->faker->dateTime(),
        ];
    }

    public function berjalan(): static
    {
        return $this->state(fn (array $attributes) => [
            'nilai' => null,
            'jumlah_benar' => null,
            'jumlah_salah' => null,
            'lulus' => null,
            'disubmit_pada' => null,
            'selesai_pada' => null,
        ]);
    }
}
