<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\MateriRepositoryInterface;
use App\Models\Materi;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class MateriRepository extends BaseRepository implements MateriRepositoryInterface
{
    public function __construct(Materi $model)
    {
        parent::__construct($model);
    }

    public function untukTingkat(int $tingkatId): Collection
    {
        return $this->model->newQuery()
            ->where('tingkat_id', $tingkatId)
            ->orderBy('urutan')
            ->get();
    }
}
