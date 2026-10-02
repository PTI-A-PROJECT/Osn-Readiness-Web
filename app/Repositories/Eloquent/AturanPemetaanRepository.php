<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AturanPemetaanRepositoryInterface;
use App\Models\AturanPemetaan;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class AturanPemetaanRepository extends BaseRepository implements AturanPemetaanRepositoryInterface
{
    public function __construct(AturanPemetaan $model)
    {
        parent::__construct($model);
    }

    public function untukTingkat(int $tingkatId): Collection
    {
        return $this->model->newQuery()
            ->where('tingkat_id', $tingkatId)
            ->get();
    }
}
