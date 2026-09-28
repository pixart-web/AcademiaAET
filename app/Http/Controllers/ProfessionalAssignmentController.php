<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfessionalAssignmentController extends Controller
{
    public function store(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('update', $child);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $professional = User::query()
            ->where('id', $data['user_id'])
            ->where('organization_id', $child->organization_id)
            ->where('role', 'professional')
            ->firstOrFail();

        ProfessionalAssignment::query()->firstOrCreate(
            ['child_profile_id' => $child->id, 'user_id' => $professional->id, 'active' => true],
            ['assigned_by_user_id' => $request->user()->id, 'started_at' => now()],
        );

        AuditLogger::log('professional.assigned', $child, ['professional_id' => $professional->id]);

        return back()->with('status', 'Terapeuta associada.');
    }

    public function destroy(Request $request, ChildProfile $child, ProfessionalAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $child);
        abort_unless($assignment->child_profile_id === $child->id, 404);

        $assignment->update(['active' => false, 'ended_at' => now()]);

        AuditLogger::log('professional.unassigned', $child, ['professional_id' => $assignment->user_id]);

        return back()->with('status', 'Associação terminada.');
    }
}
