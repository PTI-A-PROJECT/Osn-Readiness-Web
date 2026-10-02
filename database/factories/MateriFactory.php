<?php

namespace Database\Factories;

use App\Models\Kompetensi;
use App\Models\Materi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Materi>
 */
class MateriFactory extends Factory
{
    protected $model = Materi::class;

    public function definition(): array
    {
        return [
            'kompetensi_id' => Kompetensi::factory(),
            'judul' => $this->faker->sentence(3),
            'isi_materi' => $this->faker->paragraphs(5, asText: true),
            'urutan' => $this->faker->numberBetween(1, 20),
        ];
    }
}
