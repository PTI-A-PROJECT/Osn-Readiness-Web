<?php

namespace Database\Factories;

use App\Models\KonteksSoal;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KonteksSoal>
 */
class KonteksSoalFactory extends Factory
{
    protected $model = KonteksSoal::class;

    public function definition(): array
    {
        return [
            'tingkat_seleksi_id' => TingkatSeleksi::factory(),
            'deskripsi' => $this->faker->paragraphs(2, asText: true),
        ];
    }
}
