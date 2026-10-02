<?php

namespace App\Services;

use App\Contracts\Services\SyaratSimulasiServiceInterface;
use App\DTOs\SyaratSimulasi;
use App\Models\TingkatSeleksi;
use App\Models\User;

/**
 * Kerangka syarat simulasi. Pemeriksaan sungguhan (progress dan nilai latihan
 * per materi wajib) diisi di B2-B.
 *
 * Sementara dianggap belum terpenuhi supaya siswa tetap di tahap BELAJAR dan
 * pintu mulai simulasi tidak pernah salah terbuka sebelum syarat benar-benar
 * diperiksa.
 */
class SyaratSimulasiService implements SyaratSimulasiServiceInterface
{
    public function periksa(User $user, TingkatSeleksi $tingkat): SyaratSimulasi
    {
        return new SyaratSimulasi(terpenuhi: false, rincian: []);
    }
}
