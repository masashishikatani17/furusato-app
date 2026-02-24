<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $actor): bool
    {
        if ($actor->isOwner() || $actor->isRegistrar()) {
            return true;
        }
        if ($actor->isGroupAdmin()) {
            return true; // index 側で自部署に絞る
        }
        if ($actor->getDisplayRoleAttribute() === 'member') {
            return true; // index 側で自部署に絞る
        }
        return false;
    }

    public function view(User $actor, Group $group): bool
    {
        if ((int)$actor->company_id !== (int)$group->company_id) {
            return false;
        }
        if ($actor->isOwner() || $actor->isRegistrar()) {
            return true;
        }
        if ($actor->isGroupAdmin()) {
            return (int)$actor->group_id === (int)$group->id;
        }
        if ($actor->getDisplayRoleAttribute() === 'member') {
            return (int)$actor->group_id === (int)$group->id;
        }
        return false;
    }

    public function create(User $actor): bool
    {
        return $actor->isOwner() || $actor->isRegistrar();
    }

    public function update(User $actor, Group $group): bool
    {
        return ((int)$actor->company_id === (int)$group->company_id)
            && ($actor->isOwner() || $actor->isRegistrar());
    }

    public function deactivate(User $actor, Group $group): bool
    {
        return $this->update($actor, $group);
    }

    public function activate(User $actor, Group $group): bool
    {
        return $this->update($actor, $group);
    }

    public function delete(User $actor, Group $group): bool
    {
        return $this->update($actor, $group);
    }

    public function transfer(User $actor, Group $group): bool
    {
        return $this->update($actor, $group);
    }
}
