<?php

namespace App\Policies;

use App\Models\ScreeningSession;
use App\Models\User;

/**
 * A screening holds a child's name, contacts and a health-related result:
 * visible to admins of the organization and to the staff member who ran it —
 * not to every professional in the organization.
 */
class ScreeningSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function view(User $user, ScreeningSession $session): bool
    {
        return $user->organization_id === $session->organization_id
            && ($user->isAdmin() || $session->conducted_by_user_id === $user->id);
    }

    public function update(User $user, ScreeningSession $session): bool
    {
        return $this->view($user, $session);
    }
}
