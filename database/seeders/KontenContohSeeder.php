<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\Peruntukan;
use App\Models\Kompetensi;
use App\Models\Materi;
use App\Models\Quiz;
use App\Models\Simulasi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use Illuminate\Database\Seeder;

/**
 * Konten contoh untuk pengembangan dan test, bukan untuk produksi.
 *
 * Bank soal yang dihasilkan harus cukup untuk tiga putaran pre-test tanpa soal
 * berulang, satu set simulasi, dan satu latihan per materi, supaya skenario
 * siswa di B4 bisa dijalankan sebagai test.
 *
 * Ukuran bank dihitung dari aturan pemetaan bawaan. Pre-test 30 soal dengan
 * kuota 15/9/6 per level, dan langkah batas materi mengurangi kuota level itu,
 * jadi satu putaran selalu memakai tepat kuota tiap level. Tiga kali putaran
 * = 45/27/18 per materi, ditambah margin. Simulasi 30 soal dengan kuota 9/12/9
 * dipakai satu kali = 9/12/9, ditambah margin. Latihan tidak memakai pembagian
 * level dan tidak mengulang soal, jadi cukup 10 butir per materi.
 */
class KontenContohSeeder extends Seeder
{
    /**
     * Jumlah materi per tingkat, diindeks dengan urutan tingkat.
     *
     * @var array<int, int>
     */
    private const JUMLAH_MATERI = [
        1 => 5,
        2 => 10,
    ];

    /**
     * Jumlah soal per level, diindeks dengan urutan tingkat lalu peruntukan.
     *
     * @var array<int, array<string, array<string, int>>>
     */
    private const BANK_SOAL = [
        1 => [
            Peruntukan::Pretest->value => [
                Level::Mudah->value => 48,
                Level::Sedang->value => 30,
                Level::Sulit->value => 20,
            ],
            Peruntukan::Latihan->value => [
                Level::Mudah->value => 4,
                Level::Sedang->value => 3,
                Level::Sulit->value => 3,
            ],
            Peruntukan::Simulasi->value => [
                Level::Mudah->value => 12,
                Level::Sedang->value => 15,
                Level::Sulit->value => 12,
            ],
        ],
        2 => [
            Peruntukan::Pretest->value => [
                Level::Mudah->value => 48,
                Level::Sedang->value => 30,
                Level::Sulit->value => 20,
            ],
            Peruntukan::Latihan->value => [
                Level::Mudah->value => 4,
                Level::Sedang->value => 3,
                Level::Sulit->value => 3,
            ],
            Peruntukan::Simulasi->value => [
                Level::Mudah->value => 12,
                Level::Sedang->value => 15,
                Level::Sulit->value => 12,
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::JUMLAH_MATERI as $urutan => $jumlahMateri) {
            $tingkat = TingkatSeleksi::where('urutan', $urutan)->first();

            if ($tingkat === null) {
                $this->command?->warn("Tingkat urutan {$urutan} belum ada, konten contoh dilewati.");

                continue;
            }

            $materi = $this->seedMateri($tingkat, $jumlahMateri);

            $this->seedSoal($tingkat, $materi);
            $this->seedLatihan($materi);
        }

        $this->seedSimulasi();
    }

    /**
     * @return array<int, Materi>
     */
    private function seedMateri(TingkatSeleksi $tingkat, int $jumlahMateri): array
    {
        $kompetensi = Kompetensi::firstOrCreate([
            'tingkat_id' => $tingkat->id,
            'nama_kompetensi' => 'Matematika',
        ]);

        $materi = [];

        for ($urutan = 1; $urutan <= $jumlahMateri; $urutan++) {
            $materi[] = Materi::firstOrCreate(
                [
                    'tingkat_id' => $tingkat->id,
                    'urutan' => $urutan,
                ],
                [
                    'kompetensi_id' => $kompetensi->id,
                    'judul' => "Materi {$tingkat->nama_tingkat} {$urutan}",
                    'deskripsi' => "Materi contoh nomor {$urutan} untuk {$tingkat->nama_tingkat}.",
                    'isi_materi' => "## Konsep Dasar\n\nIsi materi contoh nomor {$urutan}.",
                ],
            );
        }

        return $materi;
    }

    /**
     * @param  array<int, Materi>  $materi
     */
    private function seedSoal(TingkatSeleksi $tingkat, array $materi): void
    {
        $bank = self::BANK_SOAL[$tingkat->urutan] ?? [];

        foreach ($materi as $nomor => $satuMateri) {
            foreach ($bank as $peruntukan => $perLevel) {
                foreach ($perLevel as $level => $jumlah) {
                    Soal::factory()
                        ->count($jumlah)
                        ->create([
                            'tingkat_id' => $tingkat->id,
                            'materi_id' => $satuMateri->id,
                            'level' => $level,
                            'peruntukan' => $peruntukan,
                            'pertanyaan' => sprintf(
                                'Soal contoh %s level %s materi %d.',
                                $peruntukan,
                                $level,
                                $nomor + 1,
                            ),
                        ]);
                }
            }
        }
    }

    /**
     * Satu latihan per materi, dengan jumlah_soal tidak kurang dari latihan_min_soal.
     *
     * @param  array<int, Materi>  $materi
     */
    private function seedLatihan(array $materi): void
    {
        foreach ($materi as $nomor => $satuMateri) {
            Quiz::firstOrCreate(
                ['materi_id' => $satuMateri->id],
                [
                    'nama_quiz' => 'Latihan '.($nomor + 1),
                    'deskripsi' => 'Latihan contoh.',
                    'jumlah_soal' => 10,
                ],
            );
        }
    }

    private function seedSimulasi(): void
    {
        foreach (TingkatSeleksi::orderBy('urutan')->get() as $tingkat) {
            Simulasi::firstOrCreate(
                [
                    'tingkat_id' => $tingkat->id,
                    'nama_simulasi' => 'Simulasi '.$tingkat->nama_tingkat,
                ],
                [
                    'deskripsi' => 'Simulasi contoh.',
                    'jumlah_soal' => 30,
                    'durasi_menit' => 120,
                    'is_aktif' => true,
                ],
            );
        }
    }
}
