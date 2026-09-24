<?php

namespace App\Http\Controllers;

use App\Enums\RewardType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChildHomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $child = Auth::guard('child')->user();

        $assignments = $child->assignments()
            ->whereIn('status', ['assigned', 'started'])
            ->with('activityVersion.activity')
            ->orderBy('due_at')
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'title' => $assignment->activityVersion->activity->title,
                'category' => $assignment->activityVersion->activity->category,
                'in_progress_attempt_id' => $assignment->attempts()->where('status', 'in_progress')->value('id'),
            ]);

        // "submitted" (awaiting review) and "reviewed" (feedback may be ready)
        // are kept as distinct states end-to-end, never collapsed into one
        // generic "done" bucket — see Priority 2 of docs/progress.md.
        $completed = $child->assignments()
            ->whereIn('status', ['submitted', 'reviewed'])
            ->with('activityVersion.activity', 'attempts.evaluation')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(function ($assignment) {
                $latestAttempt = $assignment->attempts->sortByDesc('attempt_number')->first();
                $evaluation = $latestAttempt?->evaluation;

                return [
                    'id' => $assignment->id,
                    'status' => $assignment->status,
                    'title' => $assignment->activityVersion->activity->title,
                    'has_feedback' => $evaluation !== null && filled($evaluation->shared_feedback),
                    'evaluated' => $evaluation !== null,
                ];
            });

        $rewardTotals = $child->rewardEvents()
            ->selectRaw('type, count(*) as count, sum(points) as points')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $achievements = collect(RewardType::cases())->map(fn (RewardType $type) => [
            'type' => $type->value,
            'count' => (int) ($rewardTotals[$type->value]->count ?? 0),
            'points' => (int) ($rewardTotals[$type->value]->points ?? 0),
        ]);

        return Inertia::render('ChildPortal/Home', [
            'assignments' => $assignments,
            'completed' => $completed,
            'achievements' => $achievements,
            'totalPoints' => (int) $rewardTotals->sum('points'),
        ]);
    }
}
