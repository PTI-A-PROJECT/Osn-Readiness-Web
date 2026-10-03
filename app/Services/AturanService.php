<?php

namespace App\Services;

use App\Contracts\Repositories\AturanPemetaanRepositoryInterface;
use App\Contracts\Services\AturanServiceInterface;
use App\DTOs\AturanTingkat;
use RuntimeException;

class AturanService implements AturanServiceInterface
{
    /** @var array<int, AturanTingkat> */
    private array $cache = [];

    public function __construct(
        private readonly AturanPemetaanRepositoryInterface $aturanRepository,
    ) {}

    public function untukTingkat(int $tingkatId): AturanTingkat
    {
        return $this->cache[$tingkatId] ??= $this->bangun($tingkatId);
    }

    private function bangun(int $tingkatId): AturanTingkat
    {
        $aturan = $this->aturanRepository->untukTingkat($tingkatId)->first();

        if (!$aturan) {
            throw new RuntimeException(
                "Aturan pemetaan untuk tingkat {$tingkatId} tidak ditemukan."
            );
        }

        return new AturanTingkat(
            tingkatId: $tingkatId,
            bobotMudah: (int) $aturan->bobot_pretest, // Using pretest bobot as mudah level bobot
            bobotSedang: (int) ($aturan->bobot_simulasi / 2), // Approximation
            bobotSulit: (int) ($aturan->bobot_simulasi / 2), // Approximation
            pretestJumlahSoal: (int) $aturan->pretest_jumlah_soal,
            persenLevelPretest: [
                'mudah' => (float) $aturan->persen_pretest_mudah,
                'sedang' => (float) $aturan->persen_pretest_sedang,
                'sulit' => (float) $aturan->persen_pretest_sulit,
            ],
            pretestMinSoalPerMateri: (int) $aturan->pretest_min_soal_per_materi,
            jumlahMateriWajib: (int) $aturan->jumlah_materi_wajib,
            latihanMinSoal: 0,
            latihanMinNilai: (float) $aturan->latihan_min_nilai,
            persenLevelSimulasi: [
                'mudah' => (float) $aturan->persen_simulasi_mudah,
                'sedang' => (float) $aturan->persen_simulasi_sedang,
                'sulit' => (float) $aturan->persen_simulasi_sulit,
            ],
            simulasiMaksPercobaan: (int) $aturan->simulasi_maks_percobaan,
            passingGrade: (float) $aturan->passing_grade_pretest,
        );
    }
}
