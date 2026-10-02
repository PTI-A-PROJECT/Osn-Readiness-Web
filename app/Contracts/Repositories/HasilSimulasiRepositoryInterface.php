<?php

namespace App\Contracts\Repositories;

use App\Models\HasilSimulasi;

interface HasilSimulasiRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Simulasi yang belum selesai dinilai pada putaran ini, kalau ada.
     */
    public function berjalan(int $userId, int $pretestId): ?HasilSimulasi;

    /**
     * Jumlah percobaan simulasi yang sudah dipakai pada satu putaran.
     */
    public function jumlahPercobaan(int $pretestId): int;
}
