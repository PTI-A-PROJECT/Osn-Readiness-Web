<?php

namespace Database\Factories;

use App\Enums\Level;
use App\Enums\Peruntukan;
use App\Enums\TipeSoal;
use App\Models\Materi;
use App\Models\Soal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Soal>
 */
class SoalFactory extends Factory
{
    protected $model = Soal::class;

    public function definition(): array
    {
        $tipe = $this->faker->randomElement([TipeSoal::PILIHAN_GANDA, TipeSoal::ISIAN]);
        $pilihan = null;
        $jawaban = null;

        if ($tipe === TipeSoal::PILIHAN_GANDA) {
            $pilihan = [
                'A' => $this->faker->sentence(),
                'B' => $this->faker->sentence(),
                'C' => $this->faker->sentence(),
                'D' => $this->faker->sentence(),
            ];
            $jawaban = $this->faker->randomElement(['A', 'B', 'C', 'D']);
        }

        return [
            'materi_id' => Materi::factory(),
            'pertanyaan' => $this->faker->sentence(),
            'pilihan' => $pilihan,
            'jawaban_benar' => $jawaban,
            'level' => $this->faker->randomElement([Level::MUDAH, Level::SEDANG, Level::SULIT]),
            'peruntukan' => $this->faker->randomElement([Peruntukan::PRETEST, Peruntukan::LATIHAN, Peruntukan::SIMULASI]),
            'tipe_soal' => $tipe,
        ];
    }
}
