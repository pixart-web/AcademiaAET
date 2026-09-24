<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Attempt;
use App\Services\AttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AttemptController extends Controller
{
    private const MAX_RECORDING_KB = 40 * 1024;

    public function start(Request $request, Assignment $assignment, AttemptService $attempts): RedirectResponse
    {
        $child = Auth::guard('child')->user();
        abort_unless($assignment->child_profile_id === $child->id, 403);

        $attempt = $attempts->startOrResume($assignment);

        return redirect()->route('child.attempts.show', $attempt);
    }

    public function show(Request $request, Attempt $attempt, AttemptService $attempts): Response
    {
        $child = Auth::guard('child')->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);

        return Inertia::render('ChildPortal/Attempt', [
            'attempt' => $attempt->only('id', 'status', 'attempt_number'),
            'steps' => $attempts->serializeSteps($attempt),
        ]);
    }

    public function saveStep(Request $request, Attempt $attempt, int $step, AttemptService $attempts): RedirectResponse
    {
        $child = Auth::guard('child')->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);

        $activityStep = $attempt->assignment->activityVersion->steps()->findOrFail($step);
        $isFileResponse = $activityStep->response_type->isRecording() || $activityStep->response_type->value === 'drawing';

        if ($isFileResponse) {
            $request->validate(['file' => ['required', 'file', 'max:'.self::MAX_RECORDING_KB]]);
        } else {
            $request->validate(['value' => ['nullable']]);
        }

        $attempts->saveStep($attempt, $activityStep, $child, $request->input('value'), $request->file('file'));

        return back();
    }

    public function submit(Request $request, Attempt $attempt, AttemptService $attempts): RedirectResponse
    {
        $child = Auth::guard('child')->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);

        if ($attempt->status->value !== 'in_progress') {
            return redirect()->route('child.home')->with('status', 'Já submetido.');
        }

        $attempts->submit($attempt, $request->string('submission_key')->value() ?: null);

        return redirect()->route('child.home')->with('status', 'Atividade submetida! Bom trabalho.');
    }
}
