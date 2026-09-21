<?php

namespace App\Http\Controllers;

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
                'in_progress_attempt_id' => $assignment->attempts()->where('status', 'in_progress')->value('id'),
            ]);

        $completed = $child->assignments()
            ->whereIn('status', ['submitted', 'reviewed'])
            ->with('activityVersion.activity')
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'status' => $assignment->status,
                'title' => $assignment->activityVersion->activity->title,
            ]);

        return Inertia::render('ChildPortal/Home', [
            'assignments' => $assignments,
            'completed' => $completed,
        ]);
    }
}
