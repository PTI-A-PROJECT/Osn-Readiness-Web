<?php

namespace Database\Factories;

use App\Models\Materi;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    public function definition(): array
    {
        return [
            'materi_id' => Materi::factory(),
            'jumlah_soal' => $this->faker->numberBetween(5, 20),
        ];
    }
}
