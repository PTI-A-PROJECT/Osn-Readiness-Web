<?php

namespace App\Policies;

use App\Models\KonteksSoal;
use App\Models\User;

class KonteksSoalPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Reload roles if not loaded
        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // Check if user has Super Admin role in any guard
        if ($user->roles()->where('name', 'Super Admin')->exists()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('konteks_soal.viewAny');
    }

    public function view(User $user, KonteksSoal $model): bool
    {
        return $user->hasPermissionTo('konteks_soal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('konteks_soal.create');
    }

    public function update(User $user, KonteksSoal $model): bool
    {
        return $user->hasPermissionTo('konteks_soal.update');
    }

    public function delete(User $user, KonteksSoal $model): bool
    {
        return $user->hasPermissionTo('konteks_soal.delete');
    }
}
