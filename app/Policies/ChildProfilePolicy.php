<?php

namespace App\Policies;

use App\Models\ChildProfile;
use App\Models\User;

class ChildProfilePolicy
{
    /**
     * Admins manage every child profile in their organization. Professionals
     * may only act on a child through an active professional_assignments row —
     * being able to manage staff accounts never implies clinical access.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function view(User $user, ChildProfile $child): bool
    {
        return $this->sameOrganization($user, $child)
            && ($user->isAdmin() || $this->isAssigned($user, $child));
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ChildProfile $child): bool
    {
        return $this->sameOrganization($user, $child) && $user->isAdmin();
    }

    public function delete(User $user, ChildProfile $child): bool
    {
        return $this->sameOrganization($user, $child) && $user->isAdmin();
    }

    public function manageClinicalData(User $user, ChildProfile $child): bool
    {
        return $this->sameOrganization($user, $child)
            && ($user->isAdmin() || $this->isAssigned($user, $child));
    }

    private function sameOrganization(User $user, ChildProfile $child): bool
    {
        return $user->organization_id === $child->organization_id;
    }

    private function isAssigned(User $user, ChildProfile $child): bool
    {
        if (! $user->isProfessional()) {
            return false;
        }

        return $child->professionalAssignments()
            ->where('user_id', $user->id)
            ->where('active', true)
            ->exists();
    }
}
