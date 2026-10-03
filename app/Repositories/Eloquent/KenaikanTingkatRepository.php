<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\KenaikanTingkatRepositoryInterface;
use App\Enums\StatusKenaikan;
use App\Models\KenaikanTingkat;
use App\Repositories\BaseRepository;

class KenaikanTingkatRepository extends BaseRepository implements KenaikanTingkatRepositoryInterface
{
    public function __construct(KenaikanTingkat $model)
    {
        parent::__construct($model);
    }

    public function adaLulus(int $userId, int $tingkatId): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('tingkat_asal_id', $tingkatId)
            ->where('status', StatusKenaikan::Lulus->value)
            ->exists();
    }
}
