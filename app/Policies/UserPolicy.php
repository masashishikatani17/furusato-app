<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function invite(User $actor): bool
    {
        return $actor->isOwner()
            || $actor->isRegistrar()
            || $actor->isGroupAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        if (! $this->sameCompany($actor, $target)) {
            return false;
        }

        if ($actor->isOwner()) {
            return true;
        }

        if ($actor->isRegistrar()) {
            return ! $target->isOwner();
        }

        if ($actor->isGroupAdmin()) {
            if ((int) $actor->group_id !== (int) $target->group_id) {
                return false;
            }

            if ($target->isOwner() || $target->isRegistrar()) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->canToggleStatus($actor, $target);
    }

    public function activate(User $actor, User $target): bool
    {
        return $this->canToggleStatus($actor, $target);
    }

    protected function sameCompany(User $actor, User $target): bool
    {
        return (int) $actor->company_id === (int) $target->company_id;
    }

    protected function canToggleStatus(User $actor, User $target): bool
    {
        if (! $this->sameCompany($actor, $target)) {
            return false;
        }

        return $actor->isOwner() || $actor->isRegistrar();
    }

    
}