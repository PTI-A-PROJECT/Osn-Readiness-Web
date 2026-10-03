<?php

namespace App\Contracts\Repositories;

use App\Models\Materi;
use Illuminate\Database\Eloquent\Collection;

interface MateriRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Materi satu tingkat dalam urutan tampilnya.
     *
     * @return Collection<int, Materi>
     */
    public function untukTingkat(int $tingkatId): Collection;
}
