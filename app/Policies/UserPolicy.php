<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function manage(User $actor): bool
    {
        return false;
    }
}