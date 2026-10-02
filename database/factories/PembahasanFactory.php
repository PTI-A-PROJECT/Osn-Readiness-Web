<?php

namespace Database\Factories;

use App\Models\Pembahasan;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pembahasan>
 */
class PembahasanFactory extends Factory
{
    protected $model = Pembahasan::class;

    public function definition(): array
    {
        return [
            'soal_id' => Soal::factory(),
            'isi_pembahasan' => $this->faker->paragraphs(3, asText: true),
        ];
    }
}
