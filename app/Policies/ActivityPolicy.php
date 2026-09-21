<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->organization_id === $activity->organization_id
            && ($user->isAdmin() || $user->isProfessional());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->organization_id === $activity->organization_id
            && ($user->isAdmin() || $activity->created_by_user_id === $user->id);
    }

    public function publish(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }

    public function archive(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->organization_id === $activity->organization_id && $user->isAdmin();
    }
}
