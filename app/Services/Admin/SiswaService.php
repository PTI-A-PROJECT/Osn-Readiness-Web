<?php

namespace App\Services\Admin;

use App\Contracts\Services\SiswaServiceInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SiswaService implements SiswaServiceInterface
{
    /**
     * Get all active siswa (paginated), excluding the authenticated user
     */
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return User::where('is_active', true)
            ->where('id', '!=', auth()->id())
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
