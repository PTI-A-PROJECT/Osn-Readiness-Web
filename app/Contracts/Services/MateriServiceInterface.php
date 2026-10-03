<?php

namespace App\Contracts\Services;

use App\Models\Materi;
use Illuminate\Support\Collection;

interface MateriServiceInterface
{
    /**
     * @return Collection<int, Materi>
     */
    public function getAll(?int $kompetensiId = null): Collection;

    public function getById(int $id): Materi;

    public function create(array $data): Materi;

    public function update(int $id, array $data): Materi;

    public function delete(int $id): bool;
}
