<?php

namespace Database\Factories;

use App\Models\TingkatSeleksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TingkatSeleksi>
 */
class TingkatSeleksiFactory extends Factory
{
    protected $model = TingkatSeleksi::class;

    public function definition(): array
    {
        return [
            'nama' => $this->faker->word(),
            'deskripsi' => $this->faker->sentence(),
            'urutan' => $this->faker->unique()->numberBetween(1, 10),
        ];
    }
}
