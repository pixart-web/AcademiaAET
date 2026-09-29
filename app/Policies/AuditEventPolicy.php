<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AuditEvent $event): bool
    {
        return $user->isAdmin() && $user->organization_id === $event->organization_id;
    }
}
