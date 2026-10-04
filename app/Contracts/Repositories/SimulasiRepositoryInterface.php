<?php

namespace App\Contracts\Repositories;

use App\Models\Simulasi;
use Illuminate\Database\Eloquent\Collection;

interface SimulasiRepositoryInterface
{
    /**
     * @return Collection<int, Simulasi>
     */
    public function untukTingkat(int $tingkatId): Collection;
}
