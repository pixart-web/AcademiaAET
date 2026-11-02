<?php

namespace App\Policies;

use App\Enums\MediaPurpose;
use App\Models\MediaAsset;
use App\Models\ProfessionalAssignment;
use App\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    /**
     * AET-RC01 finding 1: instructional content is visible to any staff
     * member in the organization, same as before. A clinical response
     * (a child's recording/drawing) is different — it requires an active
     * case assignment to that specific child, or admin, exactly the same
     * rule ChildProfilePolicy already applies to the child's profile
     * itself. Being able to manage staff accounts never implies clinical
     * access on its own (see ChildProfilePolicy's own docblock) — an
     * admin's access here is the same organization-wide clinical
     * oversight ChildProfilePolicy already grants them, not a new,
     * broader exception invented for media.
     *
     * A NULL purpose (a legacy row the backfill migration couldn't
     * classify) is treated the same as an unowned clinical response:
     * visible only to an admin, for manual review — never through the
     * general library, and never to a professional who merely happens to
     * share the organization.
     */
    public function view(User $user, MediaAsset $media): bool
    {
        if ($user->organization_id !== $media->organization_id) {
            return false;
        }

        if ($media->purpose === MediaPurpose::Instructional) {
            return $user->isAdmin() || $user->isProfessional();
        }

        if ($media->owner_child_profile_id !== null) {
            return $user->isAdmin() || $this->isAssignedToChild($user, $media->owner_child_profile_id);
        }

        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function update(User $user, MediaAsset $media): bool
    {
        if (! $this->view($user, $media)) {
            return false;
        }

        if ($media->purpose !== MediaPurpose::Instructional) {
            // Archiving/deleting a clinical response isn't a "my upload"
            // action from the didactic library — reserved for admins
            // (ordinarily done instead through ChildDataService's
            // export/erase, which handles the whole child, not one file).
            return $user->isAdmin();
        }

        return $user->isAdmin() || $media->uploaded_by_user_id === $user->id;
    }

    public function delete(User $user, MediaAsset $media): bool
    {
        return $this->update($user, $media);
    }

    private function isAssignedToChild(User $user, int $childProfileId): bool
    {
        if (! $user->isProfessional()) {
            return false;
        }

        return ProfessionalAssignment::query()
            ->where('user_id', $user->id)
            ->where('child_profile_id', $childProfileId)
            ->where('active', true)
            ->exists();
    }
}
