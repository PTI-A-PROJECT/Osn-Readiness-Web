<?php

namespace App\Policies;

use App\Models\Simulasi;
use App\Models\User;

class SimulasiPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('soal.viewAny');
    }

    public function view(User $user, Simulasi $simulasi): bool
    {
        return $user->hasPermissionTo('soal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('soal.create');
    }

    public function update(User $user, Simulasi $simulasi): bool
    {
        return $user->hasPermissionTo('soal.update');
    }

    public function delete(User $user, Simulasi $simulasi): bool
    {
        return $user->hasPermissionTo('soal.delete');
    }
}
