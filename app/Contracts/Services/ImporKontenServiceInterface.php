<?php

namespace App\Contracts\Services;

/**
 * Impor konten dari satu folder berisi Markdown materi, JSON soal, dan
 * gambar (BE-21). Upsert lewat id_sumber; database jadi sumber asli setelah
 * impor awal.
 */
interface ImporKontenServiceInterface
{
    /**
     * Jalankan impor. Bila $dryRun, laporan disusun tanpa menulis apa pun.
     *
     * @return array<string, mixed> laporan {materi, soal, laporan_lainnya}
     */
    public function impor(string $folder, bool $dryRun = false): array;
}
