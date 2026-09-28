<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Attempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $childIds = $user->isAdmin()
            ? $user->organization->childProfiles()->pluck('id')
            : $user->assignedChildProfiles()->pluck('child_profiles.id');

        $pendingEvaluations = Attempt::query()
            ->whereHas('assignment', fn ($q) => $q->whereIn('child_profile_id', $childIds))
            ->where('status', 'submitted')
            ->with(['assignment.childProfile', 'assignment.activityVersion.activity'])
            ->orderBy('submitted_at')
            ->limit(10)
            ->get();

        $inProgress = Assignment::query()
            ->whereIn('child_profile_id', $childIds)
            ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Started])
            ->with(['childProfile', 'activityVersion.activity'])
            ->orderBy('due_at')
            ->limit(10)
            ->get()
            ->map(fn (Assignment $a) => [
                ...$a->toArray(),
                'is_overdue' => $a->isOverdue(),
            ]);

        return Inertia::render('Dashboard', [
            'pendingEvaluations' => $pendingEvaluations,
            'inProgressAssignments' => $inProgress,
            'childCount' => $childIds->count(),
        ]);
    }
}
