<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    private const MAX_RECORDING_KB = 40 * 1024;

    public function show(Request $request, Attempt $attempt, AttemptService $attempts): JsonResponse
    {
        $this->assertOwnership($request, $attempt);

        return response()->json([
            'attempt' => $attempt->only('id', 'status', 'attempt_number'),
            'steps' => $attempts->serializeSteps($attempt),
        ]);
    }

    public function saveStep(Request $request, Attempt $attempt, int $step, AttemptService $attempts): JsonResponse
    {
        /** @var ChildProfile $child */
        $child = $request->user();
        $this->assertOwnership($request, $attempt);

        $activityStep = $attempt->assignment->activityVersion->steps()->findOrFail($step);
        $isFileResponse = $activityStep->response_type->isRecording() || $activityStep->response_type->value === 'drawing';

        if ($isFileResponse) {
            $request->validate(['file' => [$activityStep->isRequired() ? 'required' : 'nullable', 'file', 'max:'.self::MAX_RECORDING_KB]]);
        } else {
            $request->validate(['value' => ['nullable']]);
        }

        $response = $attempts->saveStep($attempt, $activityStep, $child, $request->input('value'), $request->file('file'));

        return response()->json(['step_response_id' => $response->id, 'answered_at' => $response->answered_at]);
    }

    public function submit(Request $request, Attempt $attempt, AttemptService $attempts): JsonResponse
    {
        $this->assertOwnership($request, $attempt);

        // Idempotent: same shape guarantee as the web submit endpoint — a
        // retried request with the same submission_key never double-submits.
        $attempt = $attempts->submit($attempt, $request->string('submission_key')->value() ?: null);

        return response()->json(['attempt' => $attempt->only('id', 'status', 'submitted_at')]);
    }

    private function assertOwnership(Request $request, Attempt $attempt): void
    {
        /** @var ChildProfile $child */
        $child = $request->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);
    }
}
