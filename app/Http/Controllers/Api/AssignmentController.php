<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ChildProfile $child */
        $child = $request->user();

        $assignments = $child->assignments()
            ->whereIn('status', ['assigned', 'started'])
            ->with('activityVersion.activity')
            ->orderBy('due_at')
            ->get()
            ->map(fn (Assignment $a) => [
                'id' => $a->id,
                'status' => $a->status,
                'title' => $a->activityVersion->activity->title,
                'category' => $a->activityVersion->activity->category,
                'due_at' => $a->due_at,
                'in_progress_attempt_id' => $a->attempts()->where('status', 'in_progress')->value('id'),
            ]);

        return response()->json(['data' => $assignments]);
    }

    public function start(Request $request, Assignment $assignment, AttemptService $attempts): JsonResponse
    {
        /** @var ChildProfile $child */
        $child = $request->user();
        abort_unless($assignment->child_profile_id === $child->id, 403);

        $attempt = $attempts->startOrResume($assignment);

        return response()->json([
            'attempt' => $attempt->only('id', 'status', 'attempt_number'),
            'steps' => $attempts->serializeSteps($attempt),
        ]);
    }
}
