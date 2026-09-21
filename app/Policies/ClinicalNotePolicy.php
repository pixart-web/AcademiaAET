<?php

namespace App\Policies;

use App\Models\ClinicalNote;
use App\Models\User;

class ClinicalNotePolicy
{
    /** Internal notes are never readable outside admin/assigned-professional — no exception. */
    public function view(User $user, ClinicalNote $note): bool
    {
        return app(ChildProfilePolicy::class)->manageClinicalData($user, $note->childProfile);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessional();
    }

    public function update(User $user, ClinicalNote $note): bool
    {
        return $note->author_user_id === $user->id;
    }
}
