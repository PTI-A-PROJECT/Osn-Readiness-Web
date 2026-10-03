<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SiswaServiceInterface
{
    public function getAll(int $perPage = 15): LengthAwarePaginator;

    public function getById(int $id): User;

    public function update(int $id, array $data): User;

    public function deactivate(int $id): User;
}
