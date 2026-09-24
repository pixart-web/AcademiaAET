<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\RewardEvent;

/**
 * Shared by the web portal (ChildFeedbackController) and the API
 * (Api\FeedbackController) — selects only evaluations.shared_feedback, never
 * clinical_notes, so there is one place that guarantees that boundary.
 */
class ChildFeedbackService
{
    /**
     * @return array{assignment: array, evaluation: ?array, achievements: array}
     */
    public function forAssignment(Assignment $assignment): array
    {
        $assignment->loadMissing(['activityVersion.activity', 'attempts' => fn ($q) => $q->orderByDesc('attempt_number')]);

        $latestAttempt = $assignment->attempts->first();
        $evaluation = $latestAttempt?->evaluations()->latest('evaluated_at')->first(['id', 'attempt_id', 'shared_feedback', 'evaluated_at']);

        $achievements = $latestAttempt
            ? RewardEvent::where('attempt_id', $latestAttempt->id)->get(['type', 'points'])
            : collect();

        return [
            'assignment' => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'title' => $assignment->activityVersion->activity->title,
            ],
            'evaluation' => $evaluation ? [
                'shared_feedback' => $evaluation->shared_feedback,
                'evaluated_at' => $evaluation->evaluated_at,
            ] : null,
            'achievements' => $achievements->map(fn ($a) => ['type' => $a->type, 'points' => $a->points])->all(),
        ];
    }
}
