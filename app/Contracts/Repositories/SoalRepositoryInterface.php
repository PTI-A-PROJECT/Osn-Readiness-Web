<?php

namespace App\Contracts\Repositories;

use App\Enums\Peruntukan;
use App\Models\Soal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SoalRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Kandidat soal untuk satu tingkat dan satu peruntukan, belum dihapus,
     * dikurangi daftar yang dikecualikan.
     *
     * @param  array<int, int>  $kecuali  soal_id yang tidak boleh diambil
     * @param  int|null  $materiId  batasi ke satu materi, dipakai latihan
     * @return Collection<int, Soal>
     */
    public function kandidat(int $tingkatId, Peruntukan $peruntukan, array $kecuali = [], ?int $materiId = null): Collection;

    /**
     * Ambil beberapa soal berdasarkan id sekaligus, dengan konteks dan
     * pembahasan ikut dimuat untuk keperluan review.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Soal>
     */
    public function banyakDenganKonteks(array $ids): Collection;

    /**
     * Soal yang sudah pernah dijawab di pengerjaan mana pun (pre-test atau
     * quiz) tidak boleh lagi diubah kunci, level, peruntukan, dan materinya.
     */
    public function sedangDipakai(Soal $soal): bool;

    /**
     * Daftar seluruh soal untuk layar admin, termasuk relasi tampilan.
     */
    public function paginasiAdmin(int $perPage = 15): LengthAwarePaginator;
}
