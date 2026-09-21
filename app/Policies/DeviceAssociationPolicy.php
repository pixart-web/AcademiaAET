<?php

namespace App\Policies;

use App\Models\DeviceAssociation;
use App\Models\User;

class DeviceAssociationPolicy
{
    public function view(User $user, DeviceAssociation $device): bool
    {
        return app(ChildProfilePolicy::class)->manageClinicalData($user, $device->childProfile);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function revoke(User $user, DeviceAssociation $device): bool
    {
        return $this->view($user, $device);
    }
}
