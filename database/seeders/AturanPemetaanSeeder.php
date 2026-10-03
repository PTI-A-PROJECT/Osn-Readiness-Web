<?php

namespace Database\Seeders;

use App\Models\AturanPemetaan;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

/**
 * Seed aturan pemetaan dengan columns individual untuk B1-E.
 * Satu baris per tingkat dengan semua konfigurasi.
 *
 * Memakai firstOrCreate supaya aman dijalankan ulang tanpa menimpa angka yang
 * sudah diubah admin: baris yang sudah ada tidak diubah nilainya.
 */
class AturanPemetaanSeeder extends Seeder
{
    /**
     * Aturan default per tingkat.
     *
     * @var array<int, array<string, int>>
     */
    private const ATURAN = [
        1 => [
            'bobot_pretest' => 30,
            'persen_pretest_mudah' => 50,
            'persen_pretest_sedang' => 30,
            'persen_pretest_sulit' => 20,
            'passing_grade_pretest' => 60,
            'bobot_simulasi' => 70,
            'persen_simulasi_mudah' => 30,
            'persen_simulasi_sedang' => 40,
            'persen_simulasi_sulit' => 30,
            'passing_grade_simulasi' => 70,
            'latihan_min_nilai' => 50,
            'pretest_jumlah_soal' => 30,
            'pretest_min_soal_per_materi' => 2,
            'simulasi_maks_percobaan' => 3,
            'jumlah_materi_wajib' => 3,
        ],
        2 => [
            'bobot_pretest' => 30,
            'persen_pretest_mudah' => 50,
            'persen_pretest_sedang' => 30,
            'persen_pretest_sulit' => 20,
            'passing_grade_pretest' => 60,
            'bobot_simulasi' => 70,
            'persen_simulasi_mudah' => 30,
            'persen_simulasi_sedang' => 40,
            'persen_simulasi_sulit' => 30,
            'passing_grade_simulasi' => 80,
            'latihan_min_nilai' => 50,
            'pretest_jumlah_soal' => 30,
            'pretest_min_soal_per_materi' => 2,
            'simulasi_maks_percobaan' => 3,
            'jumlah_materi_wajib' => 3,
        ],
    ];

    public function run(): void
    {
        foreach (self::ATURAN as $urutan => $aturan) {
            $tingkat = TingkatSeleksi::where('urutan', $urutan)->first();

            if ($tingkat === null) {
                $this->command?->warn("Tingkat urutan {$urutan} belum ada, aturannya dilewati.");

                continue;
            }

            AturanPemetaan::firstOrCreate(
                ['tingkat_id' => $tingkat->id],
                $aturan
            );
        }
    }
}
