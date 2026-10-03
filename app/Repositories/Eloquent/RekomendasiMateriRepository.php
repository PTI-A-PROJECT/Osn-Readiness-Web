<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\RekomendasiMateriRepositoryInterface;
use App\Models\RekomendasiMateri;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

class RekomendasiMateriRepository extends BaseRepository implements RekomendasiMateriRepositoryInterface
{
    public function __construct(RekomendasiMateri $model)
    {
        parent::__construct($model);
    }

    public function buatBanyak(int $pretestId, int $userId, array $baris): void
    {
        $waktu = now();

        $siap = [];

        foreach ($baris as $barisSatu) {
            $siap[] = [
                'pretest_id' => $pretestId,
                'user_id' => $userId,
                'materi_id' => $barisSatu['materi_id'],
                'prioritas' => $barisSatu['prioritas'],
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];
        }

        $this->model->newQuery()->insert($siap);
    }

    public function untukPretest(int $pretestId): Collection
    {
        return $this->model->newQuery()
            ->where('pretest_id', $pretestId)
            ->orderBy('prioritas')
            ->get();
    }
}
