<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\HasilSimulasiRepositoryInterface;
use App\Models\HasilSimulasi;
use App\Repositories\BaseRepository;

class HasilSimulasiRepository extends BaseRepository implements HasilSimulasiRepositoryInterface
{
    public function __construct(HasilSimulasi $model)
    {
        parent::__construct($model);
    }

    public function berjalan(int $userId, int $pretestId): ?HasilSimulasi
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('pretest_id', $pretestId)
            ->whereNull('selesai_pada')
            ->first();
    }

    public function jumlahPercobaan(int $pretestId): int
    {
        return $this->model->newQuery()
            ->where('pretest_id', $pretestId)
            ->count();
    }
}
