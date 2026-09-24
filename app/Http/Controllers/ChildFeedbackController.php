<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\RewardEvent;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChildFeedbackController extends Controller
{
    /**
     * Deliberately selects only `shared_feedback` from `evaluations` — never
     * touches the `clinical_notes` table, so there is nothing internal this
     * query could leak even if the view template changed carelessly later.
     */
    public function __invoke(Assignment $assignment): Response
    {
        $child = Auth::guard('child')->user();
        abort_unless($assignment->child_profile_id === $child->id, 403);

        $assignment->load(['activityVersion.activity', 'attempts' => fn ($q) => $q->orderByDesc('attempt_number')]);

        $latestAttempt = $assignment->attempts->first();
        $evaluation = $latestAttempt?->evaluations()->latest('evaluated_at')->first(['id', 'attempt_id', 'shared_feedback', 'evaluated_at']);

        $achievements = $latestAttempt
            ? RewardEvent::where('attempt_id', $latestAttempt->id)->get(['type', 'points'])
            : collect();

        return Inertia::render('ChildPortal/Feedback', [
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'title' => $assignment->activityVersion->activity->title,
            ],
            'evaluation' => $evaluation ? [
                'shared_feedback' => $evaluation->shared_feedback,
                'evaluated_at' => $evaluation->evaluated_at,
            ] : null,
            'achievements' => $achievements,
        ]);
    }
}
