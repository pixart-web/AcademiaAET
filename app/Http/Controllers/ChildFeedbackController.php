<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Services\ChildFeedbackService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChildFeedbackController extends Controller
{
    public function __invoke(Assignment $assignment, ChildFeedbackService $feedback): Response
    {
        $child = Auth::guard('child')->user();
        abort_unless($assignment->child_profile_id === $child->id, 403);

        return Inertia::render('ChildPortal/Feedback', $feedback->forAssignment($assignment));
    }
}
