<?php

namespace App\Policies;

use App\Models\User;

class SiswaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // Use a raw query to check if this user has the Super Admin role
        $isSuperAdmin = \DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'Super Admin')
            ->exists();

        if ($isSuperAdmin) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('siswa.viewAny');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('siswa.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('siswa.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('siswa.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermissionTo('siswa.delete');
    }

    public function deactivate(User $user, User $model): bool
    {
        // If before() didn't return true, this shouldn't be called
        // But if it does get called, ensure Super Admin always passes
        if (\DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', 'Super Admin')
            ->exists()
        ) {
            return true;
        }

        return $user->hasPermissionTo('siswa.deactivate');
    }
}
