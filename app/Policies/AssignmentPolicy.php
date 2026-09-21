<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        return app(ChildProfilePolicy::class)->view($user, $assignment->childProfile);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return app(ChildProfilePolicy::class)->manageClinicalData($user, $assignment->childProfile);
    }

    public function cancel(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
