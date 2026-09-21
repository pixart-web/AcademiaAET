<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function view(User $user, MediaAsset $media): bool
    {
        return $user->organization_id === $media->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function update(User $user, MediaAsset $media): bool
    {
        return $user->organization_id === $media->organization_id
            && ($user->isAdmin() || $media->uploaded_by_user_id === $user->id);
    }

    public function delete(User $user, MediaAsset $media): bool
    {
        return $this->update($user, $media);
    }
}
