<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserInvitationService
{
    /**
     * Creates the account with an unusable random password and immediately
     * sends a password-reset-style link so the invitee sets their own
     * password — there is never a shared or default credential in transit.
     */
    public function invite(Organization $organization, string $name, string $email, UserRole $role): User
    {
        $user = User::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => Str::random(40),
        ]);

        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    public function resendActivation(User $user): string
    {
        return Password::sendResetLink(['email' => $user->email]);
    }
}
