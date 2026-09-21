<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Notifications\NewAssignmentNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function store(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('manageClinicalData', $child);

        $data = $request->validate([
            'activity_id' => ['required', 'exists:activities,id'],
            'instructions_override' => ['nullable', 'string', 'max:2000'],
            'due_at' => ['nullable', 'date', 'after:now'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $activity = Activity::query()
            ->where('id', $data['activity_id'])
            ->where('organization_id', $child->organization_id)
            ->where('status', 'published')
            ->firstOrFail();

        $assignment = Assignment::create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $activity->current_version_id,
            'assigned_by_user_id' => $request->user()->id,
            'instructions_override' => $data['instructions_override'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? null,
            'status' => AssignmentStatus::Assigned,
        ]);

        $child->guardianRelationships()->where('status', 'active')->with('user')->get()
            ->each(fn ($relationship) => $relationship->user->notify(new NewAssignmentNotification($assignment)));

        return back()->with('status', 'Atividade atribuída.');
    }

    public function cancel(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorize('cancel', $assignment);

        $assignment->update(['status' => AssignmentStatus::Cancelled, 'cancelled_at' => now()]);

        return back()->with('status', 'Atribuição cancelada.');
    }
}
