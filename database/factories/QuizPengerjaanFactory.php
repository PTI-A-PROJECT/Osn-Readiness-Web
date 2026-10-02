<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizPengerjaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuizPengerjaan>
 */
class QuizPengerjaanFactory extends Factory
{
    protected $model = QuizPengerjaan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'quiz_id' => Quiz::factory(),
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
