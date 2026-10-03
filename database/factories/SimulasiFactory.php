<?php

namespace Database\Factories;

use App\Models\Simulasi;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Simulasi>
 */
class SimulasiFactory extends Factory
{
    protected $model = Simulasi::class;

    public function definition(): array
    {
        return [
            'tingkat_seleksi_id' => TingkatSeleksi::factory(),
            'nama' => $this->faker->word(),
            'jumlah_soal' => 30,
            'durasi_menit' => 120,
            'is_aktif' => false,
        ];
    }

    public function aktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_aktif' => true,
        ]);
    }
}
