<?php

namespace Database\Factories;

use App\Models\Kompetensi;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Kompetensi>
 */
class KompetensiFactory extends Factory
{
    protected $model = Kompetensi::class;

    public function definition(): array
    {
        return [
            'tingkat_seleksi_id' => TingkatSeleksi::factory(),
            'nama' => $this->faker->word(),
        ];
    }
}
