<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ChildProfile;
use App\Models\GuardianRelationship;
use App\Models\User;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuardianRelationshipController extends Controller
{
    public function store(Request $request, ChildProfile $child, UserInvitationService $invitations): RedirectResponse
    {
        $this->authorize('update', $child);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'relationship_type' => ['required', 'string', 'max:32'],
        ]);

        $guardian = User::query()
            ->where('organization_id', $child->organization_id)
            ->where('email', $data['email'])
            ->first();

        if (! $guardian) {
            $guardian = $invitations->invite($child->organization, $data['name'], $data['email'], UserRole::Guardian);
        }

        GuardianRelationship::query()->firstOrCreate([
            'child_profile_id' => $child->id,
            'user_id' => $guardian->id,
        ], [
            'relationship_type' => $data['relationship_type'],
            'status' => 'active',
        ]);

        return back()->with('status', 'Encarregado de educação associado.');
    }

    public function destroy(Request $request, ChildProfile $child, GuardianRelationship $relationship): RedirectResponse
    {
        $this->authorize('update', $child);
        abort_unless($relationship->child_profile_id === $child->id, 404);

        $relationship->update(['status' => 'inactive']);

        return back()->with('status', 'Associação terminada.');
    }
}
