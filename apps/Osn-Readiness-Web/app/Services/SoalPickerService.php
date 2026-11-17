<?php

namespace App\Services;

use App\Contracts\Randomizers\RandomizerInterface;
use App\Contracts\Repositories\MateriRepositoryInterface;
use App\Contracts\Repositories\SoalRepositoryInterface;
use App\Contracts\Services\AturanServiceInterface;
use App\Contracts\Services\SoalPickerServiceInterface;
use App\DTOs\PermintaanSoal;
use App\DTOs\SoalTerpilih;
use App\Enums\Level;
use App\Exceptions\BankSoalTidakCukupException;
use App\Support\KuotaLevel;

class SoalPickerService implements SoalPickerServiceInterface
{
    public function __construct(
        private readonly SoalRepositoryInterface $soalRepository,
        private readonly MateriRepositoryInterface $materiRepository,
        private readonly AturanServiceInterface $aturanService,
        private readonly RandomizerInterface $randomizer,
    ) {}

    public function pilih(PermintaanSoal $permintaan): SoalTerpilih
    {
        $bebas = $permintaan->tanpaPembagianLevel();

        // Langkah 2: kandidat. Level dan peruntukan sudah difilter repository,
        // soft delete dan daftar dikecualikan juga sudah ikut tertuang.
        $kandidat = $this->kelompokkan($permintaan);

        // Langkah 1: kuota per level.
        $sisa = $bebas
            ? [0 => $permintaan->jumlahSoal]
            : KuotaLevel::hitung($permintaan->jumlahSoal, $this->persenTerurut($permintaan));

        $terpilih = [];
        $kurangMateri = [];

        // Langkah 3: penuhi dulu batas materi, ambil dari level yang kuotanya
        // masih paling banyak tersisa lalu kurangi kuota level itu.
        if ($permintaan->pakaiBatasMateri()) {
            foreach ($this->materiAcak($permintaan->tingkatId) as $materiId) {
                $diambil = $this->ambilUntukMateri($materiId, $permintaan, $kandidat, $sisa, $terpilih, $bebas);
                $kurang = $permintaan->minimalSoalPerMateri - $diambil;

                if ($kurang > 0) {
                    $kurangMateri[$materiId] = $kurang;
                }
            }
        }

        // Langkah 4: isi sisa kuota tiap level. Untuk simulasi, kandidat yang
        // tidak ada di daftar dihindari didahulukan (langkah 5).
        $kurangLevel = $this->isiSisa($permintaan, $kandidat, $sisa, $terpilih, $bebas);

        // Langkah 6: kalau ada yang tak terpenuhi, gagal seluruhnya.
        if ($kurangMateri !== [] || $kurangLevel !== []) {
            throw new BankSoalTidakCukupException($this->rincianKekurangan(
                $permintaan,
                $kurangLevel,
                $kurangMateri,
            ));
        }

        // Langkah 7: acak urutan akhir, beri nomor, dan pasang bobot.
        return $this->rangkai($terpilih, $permintaan->tingkatId);
    }

    /**
     * Kelompokkan kandidat per level, lalu per materi untuk yang dipakai batas
     * materi. Struktur: [level][materi_id] => list butir, dan [level] => list butir.
     * Gabungan memuat semua level untuk mode bebas.
     *
     * @return array{perLevel: array<int, array<int, array<int, array{id: int, materi_id: int, level: Level}>>>, semua: array<int, array<int, array{id: int, materi_id: int, level: Level}>>, gabungan: array<int, array{id: int, materi_id: int, level: Level}>}
     */
    private function kelompokkan(PermintaanSoal $permintaan): array
    {
        $perLevel = [];
        $semua = [];
        $gabungan = [];

        $soal = $this->soalRepository->kandidat(
            $permintaan->tingkatId,
            $permintaan->peruntukan,
            $permintaan->soalDikecualikan,
            $permintaan->materiId,
        );

        foreach ($soal as $baris) {
            $level = $baris->level->index();
            $materiId = (int) $baris->materi_id;
            $butir = [
                'id' => (int) $baris->id,
                'materi_id' => $materiId,
                'level' => $baris->level,
                'jumlah_keluar' => max(0, (int) ($baris->jumlah_keluar ?? 0)),
            ];

            $perLevel[$level][$materiId][] = $butir;
            $semua[$level][] = $butir;
            $gabungan[] = $butir;
        }

        ksort($perLevel);

        return ['perLevel' => $perLevel, 'semua' => $semua, 'gabungan' => $gabungan];
    }

    /**
     * @param  array<int, float>  $persen  diindeks dengan nilai Level
     * @return array<int, float> diindeks 0, 1, 2 sesuai Level::index()
     */
    private function persenTerurut(PermintaanSoal $permintaan): array
    {
        $persen = [];

        foreach (Level::cases() as $level) {
            $persen[$level->index()] = $permintaan->persenLevel[$level->value] ?? 0.0;
        }

        return $persen;
    }

    /**
     * @return array<int, int>
     */
    private function materiAcak(int $tingkatId): array
    {
        $ids = $this->materiRepository->untukTingkat($tingkatId)
            ->map(fn ($materi): int => (int) $materi->id)
            ->all();

        return $this->randomizer->acak($ids);
    }

    /**
     * Ambil soal untuk satu materi pada langkah batas materi. Level dengan
     * kuota tersisa paling banyak didahulukan; bila kandidat level itu habis
     * untuk materi ini, turun ke level berikutnya.
     *
     * @param  array{perLevel: array<int, array<int, array<int, array{id: int, materi_id: int, level: Level}>>>, semua: array<int, array<int, array{id: int, materi_id: int, level: Level}>>, gabungan: array<int, array{id: int, materi_id: int, level: Level}>}  $kandidat
     * @param  array<int, int>  $sisa
     * @param  array<int, array{id: int, materi_id: int, level: Level}>  $terpilih
     */
    private function ambilUntukMateri(
        int $materiId,
        PermintaanSoal $permintaan,
        array $kandidat,
        array &$sisa,
        array &$terpilih,
        bool $bebas,
    ): int {
        $target = $permintaan->minimalSoalPerMateri;

        if ($bebas) {
            $pool = $kandidat['gabungan'];
            $pool = array_values(array_filter(
                $pool,
                fn (array $butir): bool => $butir['materi_id'] === $materiId,
            ));

            $sisaPool = $this->saringTerpilih($pool, $terpilih, $permintaan->soalDihindari);
            $take = array_slice($this->acakBerbobotKemunculan($sisaPool), 0, min($target, $sisa[0] ?? 0));

            foreach ($take as $butir) {
                $terpilih[$butir['id']] = $butir;
                $sisa[0]--;
            }

            return count($take);
        }

        $diambil = 0;

        // Level diurutkan menurut sisa kuota menurun, lalu indeks level menaik
        // sebagai pemutus seri supaya hasilnya deterministik.
        $urutLevel = array_keys($sisa);
        usort($urutLevel, function (int $a, int $b) use ($sisa): int {
            if ($sisa[$a] === $sisa[$b]) {
                return $a <=> $b;
            }

            return $sisa[$b] <=> $sisa[$a];
        });

        foreach ($urutLevel as $level) {
            if ($diambil >= $target || $sisa[$level] <= 0) {
                continue;
            }

            $pool = $kandidat['perLevel'][$level][$materiId] ?? [];
            $pool = $this->saringTerpilih($pool, $terpilih, $permintaan->soalDihindari);

            if ($pool === []) {
                continue;
            }

            $take = array_slice(
                $this->acakBerbobotKemunculan($pool),
                0,
                min($target - $diambil, $sisa[$level]),
            );

            foreach ($take as $butir) {
                $terpilih[$butir['id']] = $butir;
                $sisa[$level]--;
                $diambil++;
            }
        }

        return $diambil;
    }

    /**
     * Buang butir yang sudah terpakai atau ada di daftar dihindari.
     *
     * @param  array<int, array{id: int, materi_id: int, level: Level}>  $pool
     * @param  array<int, array{id: int, materi_id: int, level: Level}>  $terpilih
     * @param  array<int, int>  $dihindari
     * @return array<int, array{id: int, materi_id: int, level: Level}>
     */
    private function saringTerpilih(array $pool, array $terpilih, array $dihindari): array
    {
        $dihindari = array_flip($dihindari);

        return array_values(array_filter(
            $pool,
            fn (array $butir): bool => ! isset($terpilih[$butir['id']]) && ! isset($dihindari[$butir['id']]),
        ));
    }

    /**
     * @param  array{perLevel: array<int, array<int, array<int, array{id: int, materi_id: int, level: Level}>>>, semua: array<int, array<int, array{id: int, materi_id: int, level: Level}>>, gabungan: array<int, array{id: int, materi_id: int, level: Level}>}  $kandidat
     * @param  array<int, int>  $sisa
     * @param  array<int, array{id: int, materi_id: int, level: Level}>  $terpilih
     * @return array<int, int> sisa kuota per indeks level yang tidak terpenuhi
     */
    private function isiSisa(
        PermintaanSoal $permintaan,
        array $kandidat,
        array &$sisa,
        array &$terpilih,
        bool $bebas,
    ): array {
        $kurang = [];

        foreach ($sisa as $level => $butuh) {
            $pool = $bebas
                ? $kandidat['gabungan']
                : ($kandidat['semua'][$level] ?? []);

            // Langkah 5: kandidat yang tidak ada di daftar dihindari
            // didahulukan; soal yang dihindari baru dipakai bila kandidat
            // lain habis.
            $diprioritas = $this->saringTerpilih($pool, $terpilih, $permintaan->soalDihindari);
            $take = array_slice($this->acakBerbobotKemunculan($diprioritas), 0, $butuh);
            $kurangLevelIni = $butuh - count($take);

            foreach ($take as $butir) {
                $terpilih[$butir['id']] = $butir;
            }

            if ($kurangLevelIni > 0 && $permintaan->menghindari()) {
                $penghindaran = $this->saringTerpilih($pool, $terpilih, []);
                $take = array_merge(
                    $take,
                    array_slice($this->acakBerbobotKemunculan($penghindaran), 0, $kurangLevelIni),
                );
                $kurangLevelIni = $butuh - count($take);
            }

            foreach ($take as $butir) {
                $terpilih[$butir['id']] = $butir;
            }

            $kurang[$level] = $kurangLevelIni;
        }

        return array_filter($kurang, fn (int $sisa): bool => $sisa > 0);
    }

    /**
     * Acak kandidat dengan bobot berbanding terbalik terhadap jumlah kemunculan
     * (W_i = 1 / (jumlah_keluar + 1)). Soal yang belum/jarang keluar mendapat
     * peluang lebih besar daripada soal yang sudah sering keluar.
     *
     * Bila seluruh soal di pool memiliki jumlah_keluar yang sama (mis. 0),
     * pengacakan langsung didelegasikan ke randomizer standar.
     *
     * @param  array<int, array{id: int, materi_id: int, level: Level, jumlah_keluar?: int}>  $pool
     * @return array<int, array{id: int, materi_id: int, level: Level, jumlah_keluar?: int}>
     */
    private function acakBerbobotKemunculan(array $pool): array
    {
        if (count($pool) <= 1) {
            return $this->randomizer->acak($pool);
        }

        $frekuensi = array_map(
            fn (array $butir): int => (int) ($butir['jumlah_keluar'] ?? 0),
            $pool,
        );

        if (min($frekuensi) === max($frekuensi)) {
            return $this->randomizer->acak($pool);
        }

        // Bobot peluang W_i = 1 / (f_i + 1) direpresentasikan sebagai tiket
        // proporsional (skala 12: keluar 0x -> 12 tiket, 1x -> 6 tiket,
        // 2x -> 4 tiket, 3x -> 3 tiket, dst.), lalu diacak dan diambil unik.
        $tiket = [];

        foreach ($pool as $butir) {
            $keluar = max(0, (int) ($butir['jumlah_keluar'] ?? 0));
            $jumlahTiket = max(1, (int) round(12 / ($keluar + 1)));

            for ($i = 0; $i < $jumlahTiket; $i++) {
                $tiket[] = $butir;
            }
        }

        $tiketAcak = $this->randomizer->acak($tiket);
        $unik = [];

        foreach ($tiketAcak as $butir) {
            if (! isset($unik[$butir['id']])) {
                $unik[$butir['id']] = $butir;
            }
        }

        return array_values($unik);
    }

    /**
     * @param  array<int, array{id: int, materi_id: int, level: Level}>  $terpilih
     */
    private function rangkai(array $terpilih, int $tingkatId): SoalTerpilih
    {
        $aturan = $this->aturanService->untukTingkat($tingkatId);
        $butir = $this->randomizer->acak(array_values($terpilih));

        $hasil = [];

        foreach (array_values($butir) as $urutan => $item) {
            $hasil[] = [
                'soal_id' => $item['id'],
                'materi_id' => $item['materi_id'],
                'level' => $item['level'],
                'bobot' => $aturan->bobot($item['level']),
                'urutan' => $urutan + 1,
            ];
        }

        return new SoalTerpilih($hasil);
    }

    /**
     * @param  array<int, int>  $kurangLevel  indeks level => jumlah kurang
     * @param  array<int, int>  $kurangMateri  materi_id => jumlah kurang
     */
    private function rincianKekurangan(PermintaanSoal $permintaan, array $kurangLevel, array $kurangMateri): string
    {
        $rincian = [
            'tingkat_id' => $permintaan->tingkatId,
            'peruntukan' => $permintaan->peruntukan->value,
            'jumlah_soal' => $permintaan->jumlahSoal,
        ];

        if ($kurangLevel !== []) {
            $rincian['kurang_per_level'] = [];

            foreach ($kurangLevel as $level => $jumlah) {
                $rincian['kurang_per_level'][Level::cases()[$level]->value] = $jumlah;
            }
        }

        if ($kurangMateri !== []) {
            $rincian['kurang_per_materi'] = array_map(
                fn (int $kurang): int => $kurang,
                $kurangMateri,
            );
        }

        return (string) json_encode($rincian);
    }
}
