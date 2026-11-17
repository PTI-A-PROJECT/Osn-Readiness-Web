<?php

namespace Database\Seeders;

use App\Enums\Peruntukan;
use App\Models\HasilSimulasi;
use App\Models\HasilSimulasiJawaban;
use App\Models\Pretest;
use App\Models\PretestJawaban;
use App\Models\Quiz;
use App\Models\QuizJawaban;
use App\Models\QuizPengerjaan;
use App\Models\Simulasi;
use App\Models\Soal;
use App\Models\TingkatSeleksi;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder data pengerjaan analitik contoh (khusus environment local) supaya
 * halaman Log Aktivitas & Analisis (Attempt 1 vs 2, Persentase Kali Keluar,
 * dan 4 Tes Klasik CTT) langsung menampilkan data statistik yang realistis.
 *
 * Tidak menyentuh akun `siswa@example.com` milik E2E test.
 */
class AnalisisContohSeeder extends Seeder
{
    public function run(): void
    {
        $kabupaten = TingkatSeleksi::query()->where('urutan', 1)->first();

        if (! $kabupaten instanceof TingkatSeleksi) {
            return;
        }

        // Idempoten: bila sudah ada pengerjaan dari siswa analitik, lewati.
        $cek = User::query()->where('email', 'analitik1@osn.example.com')->first();
        if ($cek !== null && Pretest::query()->where('user_id', $cek->id)->exists()) {
            return;
        }

        $daftarProfil = [
            ['nama' => 'Rizky Pratama', 'email' => 'analitik1@osn.example.com', 'kemampuan' => 0.88, 'attempts' => 3],
            ['nama' => 'Nadia Putri', 'email' => 'analitik2@osn.example.com', 'kemampuan' => 0.82, 'attempts' => 2],
            ['nama' => 'Fajar Nugroho', 'email' => 'analitik3@osn.example.com', 'kemampuan' => 0.76, 'attempts' => 2],
            ['nama' => 'Siti Aminah', 'email' => 'analitik4@osn.example.com', 'kemampuan' => 0.68, 'attempts' => 3],
            ['nama' => 'Bagas Saputra', 'email' => 'analitik5@osn.example.com', 'kemampuan' => 0.62, 'attempts' => 2],
            ['nama' => 'Dewi Lestari', 'email' => 'analitik6@osn.example.com', 'kemampuan' => 0.55, 'attempts' => 2],
            ['nama' => 'Hendra Wijaya', 'email' => 'analitik7@osn.example.com', 'kemampuan' => 0.48, 'attempts' => 1],
            ['nama' => 'Intan Permata', 'email' => 'analitik8@osn.example.com', 'kemampuan' => 0.40, 'attempts' => 2],
            ['nama' => 'Dimas Aditya', 'email' => 'analitik9@osn.example.com', 'kemampuan' => 0.32, 'attempts' => 1],
            ['nama' => 'Aulia Rahman', 'email' => 'analitik10@osn.example.com', 'kemampuan' => 0.25, 'attempts' => 1],
        ];

        $soalSimulasi = Soal::query()
            ->where('tingkat_id', $kabupaten->id)
            ->where('peruntukan', Peruntukan::Simulasi->value)
            ->orderBy('id')
            ->limit(30)
            ->get();

        $soalPretest = Soal::query()
            ->where('tingkat_id', $kabupaten->id)
            ->where('peruntukan', Peruntukan::Pretest->value)
            ->orderBy('id')
            ->limit(25)
            ->get();

        $simulasi = Simulasi::query()->where('tingkat_id', $kabupaten->id)->first();
        $quizzes = Quiz::query()
            ->whereHas('materi', fn ($q) => $q->where('tingkat_id', $kabupaten->id))
            ->orderBy('id')
            ->limit(3)
            ->get();

        foreach ($daftarProfil as $idx => $profil) {
            $user = User::withTrashed()->firstOrNew(['email' => $profil['email']]);
            $user->fill([
                'name' => $profil['nama'],
                'password' => 'password',
                'is_active' => true,
                'tingkat_aktif_id' => $kabupaten->id,
            ]);
            if ($user->trashed()) {
                $user->restore();
            }
            $user->save();
            $user->assignRole('siswa');

            $thetaDasar = $profil['kemampuan'];
            $jumlahAttempt = $profil['attempts'];

            // 1. Buat Pretest (1x atau 2x putaran)
            $putaranPretest = $jumlahAttempt >= 2 ? 2 : 1;
            $pretestTerakhir = null;

            for ($att = 1; $att <= $putaranPretest; $att++) {
                $waktu = now()->subDays(14 - ($idx + $att * 2))->subHours($att);
                $theta = min(0.95, $thetaDasar + ($att - 1) * 0.12);

                $pretest = Pretest::create([
                    'user_id' => $user->id,
                    'tingkat_id' => $kabupaten->id,
                    'created_at' => $waktu,
                    'updated_at' => $waktu->copy()->addMinutes(35),
                    'disubmit_pada' => $waktu->copy()->addMinutes(35),
                    'selesai_pada' => $waktu->copy()->addMinutes(35),
                    'nilai' => 0,
                ]);

                // Ambil subset soal supaya frekuensi kemunculan bervariasi
                $subsetPretest = $soalPretest->slice(0, max(10, 22 - ($idx % 4) * 2))->values();
                $poinBenar = 0;
                $poinTotal = 0;

                foreach ($subsetPretest as $urutan => $s) {
                    $bobot = $s->level->value === 'sulit' ? 3 : ($s->level->value === 'sedang' ? 2 : 1);
                    $benar = $this->apakahBenar($idx, (int) $s->id, $s->level->value, $theta, $att);
                    $poinTotal += $bobot;
                    if ($benar) {
                        $poinBenar += $bobot;
                    }

                    PretestJawaban::create([
                        'pretest_id' => $pretest->id,
                        'soal_id' => $s->id,
                        'urutan' => $urutan + 1,
                        'bobot' => $bobot,
                        'jawaban_user' => $benar ? 'A' : 'B',
                        'status_benar' => $benar,
                    ]);
                }

                $nilaiPretest = $poinTotal > 0 ? round(($poinBenar / $poinTotal) * 100, 2) : 0;
                $pretest->update(['nilai' => $nilaiPretest]);
                $pretestTerakhir = $pretest;
            }

            // 2. Buat Pengerjaan Latihan (Quiz) untuk Attempt 1, 2, 3
            foreach ($quizzes as $qIdx => $quiz) {
                $soalLatihan = Soal::query()
                    ->where('materi_id', $quiz->materi_id)
                    ->where('peruntukan', Peruntukan::Latihan->value)
                    ->orderBy('id')
                    ->limit(12)
                    ->get();

                if ($soalLatihan->isEmpty()) {
                    continue;
                }

                $targetAttempt = max(1, $jumlahAttempt - ($qIdx % 2));

                for ($att = 1; $att <= $targetAttempt; $att++) {
                    $waktu = now()->subDays(10 - $att * 2)->subHours($idx + $qIdx);
                    // Pada attempt ke-2 dan ke-3 kemampuan efektif meningkat karena belajar pembahasan
                    $theta = min(0.96, $thetaDasar + ($att - 1) * 0.16);
                    $subset = $soalLatihan->slice(0, max(8, 10 - ($att % 2)))->values();

                    $pengerjaan = QuizPengerjaan::create([
                        'user_id' => $user->id,
                        'quiz_id' => $quiz->id,
                        'created_at' => $waktu,
                        'updated_at' => $waktu->copy()->addMinutes(20),
                        'disubmit_pada' => $waktu->copy()->addMinutes(20),
                        'selesai_pada' => $waktu->copy()->addMinutes(20),
                        'nilai' => 0,
                    ]);

                    $poinBenar = 0;
                    $poinTotal = 0;

                    foreach ($subset as $urutan => $s) {
                        $bobot = $s->level->value === 'sulit' ? 3 : ($s->level->value === 'sedang' ? 2 : 1);
                        $benar = $this->apakahBenar($idx, (int) $s->id, $s->level->value, $theta, $att);
                        $poinTotal += $bobot;
                        if ($benar) {
                            $poinBenar += $bobot;
                        }

                        QuizJawaban::create([
                            'pengerjaan_id' => $pengerjaan->id,
                            'soal_id' => $s->id,
                            'urutan' => $urutan + 1,
                            'bobot' => $bobot,
                            'jawaban_user' => $benar ? 'A' : 'C',
                            'status_benar' => $benar,
                        ]);
                    }

                    $skor = $poinTotal > 0 ? round(($poinBenar / $poinTotal) * 100, 2) : 0;
                    $pengerjaan->update(['nilai' => $skor]);
                }
            }

            // 3. Buat Pengerjaan Simulasi (Attempt 1, 2, 3)
            if ($simulasi instanceof Simulasi && $pretestTerakhir instanceof Pretest && $soalSimulasi->isNotEmpty()) {
                for ($att = 1; $att <= $jumlahAttempt; $att++) {
                    $waktu = now()->subDays(7 - $att * 2)->subHours($idx);
                    $theta = min(0.95, $thetaDasar + ($att - 1) * 0.14);

                    // Variasikan jumlah soal yang keluar agar frekuensi kemunculan tiap soal berbeda
                    $ambilJumlah = max(15, 25 - (($idx + $att) % 4) * 2);
                    $subsetSim = $soalSimulasi->slice(0, $ambilJumlah)->values();

                    $hasilSim = HasilSimulasi::create([
                        'user_id' => $user->id,
                        'simulasi_id' => $simulasi->id,
                        'pretest_id' => $pretestTerakhir->id,
                        'mulai_pada' => $waktu,
                        'batas_pada' => $waktu->copy()->addMinutes(120),
                        'disubmit_pada' => $waktu->copy()->addMinutes(75),
                        'selesai_pada' => $waktu->copy()->addMinutes(75),
                        'nilai' => 0,
                        'jumlah_benar' => 0,
                        'jumlah_salah' => 0,
                        'lulus' => false,
                    ]);

                    $poinBenar = 0;
                    $poinTotal = 0;
                    $jmlBenar = 0;

                    foreach ($subsetSim as $urutan => $s) {
                        $bobot = $s->level->value === 'sulit' ? 3 : ($s->level->value === 'sedang' ? 2 : 1);
                        $benar = $this->apakahBenar($idx, (int) $s->id, $s->level->value, $theta, $att);
                        $poinTotal += $bobot;
                        if ($benar) {
                            $poinBenar += $bobot;
                            $jmlBenar++;
                        }

                        HasilSimulasiJawaban::create([
                            'hasil_simulasi_id' => $hasilSim->id,
                            'soal_id' => $s->id,
                            'urutan' => $urutan + 1,
                            'bobot' => $bobot,
                            'jawaban_user' => $benar ? 'B' : 'D',
                            'status_benar' => $benar,
                        ]);
                    }

                    $skorSim = $poinTotal > 0 ? round(($poinBenar / $poinTotal) * 100, 2) : 0;
                    $hasilSim->update([
                        'nilai' => $skorSim,
                        'jumlah_benar' => $jmlBenar,
                        'jumlah_salah' => $subsetSim->count() - $jmlBenar,
                        'lulus' => $skorSim >= 70.0,
                    ]);
                }
            }
        }
    }

    /**
     * Simulasi respons butir deterministik berbasis model IRT/CTT sederhana:
     * - Mayoritas soal berdaya beda positif (siswa theta tinggi cenderung benar).
     * - Sebagian kecil soal dibuat terlalu mudah/sukar atau mengecoh supaya
     *   tabel 4 tes klasik menampilkan variasi status (Ideal, Revisi, Cek Kunci).
     */
    private function apakahBenar(int $userIdx, int $soalId, string $level, float $theta, int $attempt): bool
    {
        $kesulitanDasar = match ($level) {
            'mudah' => 0.28,
            'sedang' => 0.52,
            'sulit' => 0.72,
            default => 0.50,
        };

        // Variasi per soal_id
        $offsetSoal = (($soalId * 17) % 21 - 10) / 50.0; // -0.20 .. +0.20
        $target = max(0.15, min(0.88, $kesulitanDasar + $offsetSoal));

        // 1 dari 13 soal disimulasikan sebagai soal mengecoh (daya beda negatif)
        if ($soalId % 13 === 0) {
            return $theta < 0.55;
        }

        // Gangguan deterministik kecil per kombinasi user + soal + attempt
        $noise = ((($userIdx + 3) * 31 + $soalId * 7 + $attempt * 13) % 19 - 9) / 60.0;

        return ($theta + $noise) >= $target;
    }
}
