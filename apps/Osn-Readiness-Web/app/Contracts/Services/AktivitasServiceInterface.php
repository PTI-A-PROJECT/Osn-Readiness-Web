<?php

namespace App\Contracts\Services;

use App\Models\User;
use DateTimeInterface;

interface AktivitasServiceInterface
{
    /**
     * Mencatat satu kejadian. Aksi yang sama untuk subjek yang sama dalam
     * 60 detik digabung ke baris terakhir supaya tabel tidak membengkak.
     *
     * @param  array<string, mixed>  $meta
     */
    public function catat(
        ?User $user,
        string $aksi,
        string $kategori = 'akun',
        array $meta = [],
        ?string $subjekTipe = null,
        ?int $subjekId = null,
    ): void;

    /**
     * Timeline aktivitas seorang user: gabungan log_aktivitas (event akun dan
     * admin) dengan tabel domain (pretest, latihan, simulasi, materi, tingkat).
     * Hasil dikelompokkan per tanggal, urut terbaru dulu.
     *
     * @return list<array{tanggal: string, entri: list<array<string, mixed>>}>
     */
    public function timeline(?int $userId, ?DateTimeInterface $dari = null, ?DateTimeInterface $sampai = null, ?string $kategori = null): array;

    /**
     * Rincian mentah untuk satu aksi pada satu tanggal.
     *
     * @return list<array<string, mixed>>
     */
    public function detail(?int $userId, string $aksi, string $tanggal): array;
}
