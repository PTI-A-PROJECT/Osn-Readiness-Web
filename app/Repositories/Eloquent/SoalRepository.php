<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SoalRepositoryInterface;
use App\Enums\Peruntukan;
use App\Models\Soal;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class SoalRepository extends BaseRepository implements SoalRepositoryInterface
{
    public function __construct(Soal $model)
    {
        parent::__construct($model);
    }

    public function kandidat(int $tingkatId, Peruntukan $peruntukan, array $kecuali = [], ?int $materiId = null): Collection
    {
        return $this->model->newQuery()
            ->where('tingkat_id', $tingkatId)
            ->where('peruntukan', $peruntukan->value)
            // newQuery() sudah menerapkan soft delete, jadi soal yang
            // dihapus tidak ikut terambil.
            ->when($kecuali !== [], fn ($query) => $query->whereNotIn('id', $kecuali))
            ->when($materiId !== null, fn ($query) => $query->where('materi_id', $materiId))
            ->orderBy('id')
            ->get();
    }

    public function banyakDenganKonteks(array $ids): Collection
    {
        return $this->model->newQuery()
            ->with(['konteks', 'pembahasan'])
            // Memakai withTrashed karena soal yang sudah dipakai pengerjaan
            // tetap boleh ditampilkan di review meski di-soft delete admin.
            ->withTrashed()
            ->whereIn('id', $ids)
            ->get();
    }
}
