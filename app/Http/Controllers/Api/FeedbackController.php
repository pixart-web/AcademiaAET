<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Services\ChildFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function __invoke(Request $request, Assignment $assignment, ChildFeedbackService $feedback): JsonResponse
    {
        /** @var ChildProfile $child */
        $child = $request->user();
        abort_unless($assignment->child_profile_id === $child->id, 403);

        return response()->json($feedback->forAssignment($assignment));
    }
}
