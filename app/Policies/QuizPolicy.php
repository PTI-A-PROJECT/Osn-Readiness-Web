<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
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

    public function view(User $user, Quiz $quiz): bool
    {
        return $user->hasPermissionTo('soal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('soal.create');
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->hasPermissionTo('soal.update');
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->hasPermissionTo('soal.delete');
    }
}
