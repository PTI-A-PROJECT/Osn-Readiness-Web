<?php

namespace App\DTOs;

/**
 * Hasil pemeriksaan syarat simulasi untuk satu tingkat.
 *
 * $rincian memuat satu baris per materi wajib: materi_id, judul, selesai,
 * nilai_latihan, batas, dan latihan_belum_tersedia. Frontend memakainya untuk
 * menampilkan pesan yang tepat, admin untuk tahu apa yang harus dilengkapi.
 *
 * @phpstan-type RincianMateri array{
 *     materi_id: int,
 *     judul: string,
 *     selesai: bool,
 *     nilai_latihan: float|null,
 *     batas: float,
 *     latihan_belum_tersedia: bool
 * }
 */
final class SyaratSimulasi
{
    /**
     * @param  array<int, array<string, mixed>>  $rincian
     */
    public function __construct(
        public readonly bool $terpenuhi,
        public readonly array $rincian = [],
    ) {}
}
