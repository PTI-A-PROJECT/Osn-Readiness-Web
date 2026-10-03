<?php

namespace App\Services\Admin;

use App\Contracts\Services\SiswaServiceInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;

class SiswaService implements SiswaServiceInterface
{
    /**
     * Get all active siswa (paginated)
     */
    public function getAll(int $perPage = 15): Paginator
    {
        return User::where('is_active', true)
            ->paginate($perPage);
    }

    /**
     * Get siswa by id (only active)
     */
    public function getById(int $id): User
    {
        return User::where('is_active', true)->findOrFail($id);
    }

    /**
     * Update siswa (name only)
     */
    public function update(int $id, array $data): User
    {
        $user = $this->getById($id);
        $user->update($data);

        return $user;
    }

    /**
     * Deactivate siswa and revoke all tokens
     */
    public function deactivate(int $id): User
    {
        $user = $this->getById($id);
        $user->update(['is_active' => false]);

        // Revoke all tokens
        $user->tokens()->delete();

        return $user;
    }

    /**
     * Soft delete siswa
     */
    public function delete(int $id): void
    {
        $user = $this->getById($id);
        $user->delete();
    }
}
