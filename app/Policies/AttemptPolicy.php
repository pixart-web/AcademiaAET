<?php

namespace App\Policies;

use App\Models\Attempt;
use App\Models\User;

class AttemptPolicy
{
    /**
     * Staff-side (professional/admin) view of an attempt. Child-side access is
     * enforced separately in controllers via the "child" guard, since a
     * ChildProfile is not a staff User and never carries clinical permissions.
     */
    public function view(User $user, Attempt $attempt): bool
    {
        return app(ChildProfilePolicy::class)->view($user, $attempt->assignment->childProfile);
    }

    public function evaluate(User $user, Attempt $attempt): bool
    {
        return app(ChildProfilePolicy::class)->manageClinicalData($user, $attempt->assignment->childProfile);
    }
}
