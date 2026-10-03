<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\Peruntukan;
use App\Enums\TipeSoal;
use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Pembahasan;
use App\Models\Quiz;
use App\Models\Simulasi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

class KontenContohSeeder extends Seeder
{
    public function run(): void
    {
        // Kabupaten Level
        $kabupaten = TingkatSeleksi::where('nama', 'Kabupaten')->first();
        if ($kabupaten) {
            $this->createKonten($kabupaten, 5, 10);
        }

        // Provinsi Level
        $provinsi = TingkatSeleksi::where('nama', 'Provinsi')->first();
        if ($provinsi) {
            $this->createKonten($provinsi, 10, 20);
        }
    }

    private function createKonten(TingkatSeleksi $tingkat, int $materiCount, int $soalPerMateri): void
    {
        // Create competencies
        $kompetensi = Kompetensi::firstOrCreate(
            ['tingkat_seleksi_id' => $tingkat->id, 'nama' => 'Matematika'],
            ['nama' => 'Matematika']
        );

        // Create materials
        for ($m = 1; $m <= $materiCount; $m++) {
            $materi = Materi::firstOrCreate(
                ['kompetensi_id' => $kompetensi->id, 'judul' => "Materi {$tingkat->nama} - {$m}"],
                [
                    'kompetensi_id' => $kompetensi->id,
                    'judul' => "Materi {$tingkat->nama} - {$m}",
                    'isi_materi' => "Ini adalah isi materi $m untuk tingkat {$tingkat->nama}",
                    'urutan' => $m,
                ]
            );

            // Create questions for this material
            for ($s = 1; $s <= $soalPerMateri; $s++) {
                $pilihan = [
                    'A' => "Pilihan A untuk soal {$s}",
                    'B' => "Pilihan B untuk soal {$s}",
                    'C' => "Pilihan C untuk soal {$s}",
                    'D' => "Pilihan D untuk soal {$s}",
                ];

                $soal = Soal::firstOrCreate(
                    ['materi_id' => $materi->id, 'pertanyaan' => "Soal {$s} Materi {$m}"],
                    [
                        'materi_id' => $materi->id,
                        'pertanyaan' => "Soal {$s} Materi {$m} untuk {$tingkat->nama}",
                        'pilihan' => $pilihan,
                        'jawaban_benar' => 'C',
                        'level' => $s % 3 === 0 ? Level::SULIT : ($s % 2 === 0 ? Level::SEDANG : Level::MUDAH),
                        'peruntukan' => Peruntukan::PRETEST,
                        'tipe_soal' => TipeSoal::PILIHAN_GANDA,
                    ]
                );

                // Create discussion for this question
                Pembahasan::firstOrCreate(
                    ['soal_id' => $soal->id],
                    [
                        'soal_id' => $soal->id,
                        'isi_pembahasan' => "Pembahasan untuk soal {$s}: Jawaban yang benar adalah C karena...",
                    ]
                );

                // Create similar questions for practice and simulation
                foreach ([Peruntukan::LATIHAN, Peruntukan::SIMULASI] as $peruntukan) {
                    $soalLatihan = Soal::firstOrCreate(
                        ['materi_id' => $materi->id, 'pertanyaan' => "Soal {$s} Materi {$m} - {$peruntukan->value}"],
                        [
                            'materi_id' => $materi->id,
                            'pertanyaan' => "Soal {$s} Materi {$m} ({$peruntukan->label()}) untuk {$tingkat->nama}",
                            'pilihan' => $pilihan,
                            'jawaban_benar' => 'C',
                            'level' => $s % 3 === 0 ? Level::SULIT : ($s % 2 === 0 ? Level::SEDANG : Level::MUDAH),
                            'peruntukan' => $peruntukan,
                            'tipe_soal' => TipeSoal::PILIHAN_GANDA,
                        ]
                    );

                    Pembahasan::firstOrCreate(
                        ['soal_id' => $soalLatihan->id],
                        [
                            'soal_id' => $soalLatihan->id,
                            'isi_pembahasan' => "Pembahasan untuk soal {$s} ({$peruntukan->label()}): Jawaban yang benar adalah C karena...",
                        ]
                    );
                }
            }

            // Create quiz for material
            Quiz::firstOrCreate(
                ['materi_id' => $materi->id],
                [
                    'materi_id' => $materi->id,
                    'jumlah_soal' => min(10, $soalPerMateri),
                ]
            );
        }

        // Create simulation for this level
        Simulasi::firstOrCreate(
            ['tingkat_seleksi_id' => $tingkat->id, 'nama' => "Simulasi {$tingkat->nama}"],
            [
                'tingkat_seleksi_id' => $tingkat->id,
                'nama' => "Simulasi {$tingkat->nama}",
                'jumlah_soal' => 30,
                'durasi_menit' => 120,
                'is_aktif' => false,
            ]
        );
    }
}
