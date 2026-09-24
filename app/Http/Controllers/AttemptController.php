<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\MediaKind;
use App\Enums\ResponseType;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\MediaAsset;
use App\Models\StepResponse;
use App\Notifications\AttemptSubmittedNotification;
use App\Services\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttemptController extends Controller
{
    private const RECORDING_MIME_BY_TYPE = [
        'voice_recording' => ['audio/webm', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/ogg'],
        'video_recording' => ['video/webm', 'video/mp4'],
    ];

    private const MAX_RECORDING_KB = 40 * 1024;

    public function start(Request $request, Assignment $assignment): RedirectResponse
    {
        $child = Auth::guard('child')->user();
        abort_unless($assignment->child_profile_id === $child->id, 403);
        abort_unless($assignment->hasAttemptsRemaining(), 422, 'Sem mais tentativas disponíveis.');
        abort_if($assignment->status === AssignmentStatus::Cancelled, 422);

        $attempt = $assignment->attempts()->where('status', 'in_progress')->first();

        if (! $attempt) {
            $attempt = DB::transaction(function () use ($assignment) {
                $assignment->update(['status' => AssignmentStatus::Started]);

                return $assignment->attempts()->create([
                    'attempt_number' => $assignment->nextAttemptNumber(),
                    'status' => AttemptStatus::InProgress,
                    'started_at' => now(),
                ]);
            });
        }

        return redirect()->route('child.attempts.show', $attempt);
    }

    public function show(Request $request, Attempt $attempt): Response
    {
        $child = Auth::guard('child')->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);

        $attempt->load(['assignment.activityVersion.steps.instructionMedia', 'stepResponses']);

        $steps = $attempt->assignment->activityVersion->steps->map(function ($step) use ($attempt) {
            $response = $attempt->stepResponses->firstWhere('activity_step_id', $step->id);

            return [
                'id' => $step->id,
                'position' => $step->position,
                'title' => $step->title,
                'body' => $step->body,
                'response_type' => $step->response_type,
                'response_config' => $step->response_config,
                'instruction_media_url' => $step->instructionMedia
                    ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $step->instructionMedia->id])
                    : null,
                'answered' => $response !== null,
                'value' => $response?->value,
            ];
        });

        return Inertia::render('ChildPortal/Attempt', [
            'attempt' => $attempt->only('id', 'status', 'attempt_number'),
            'steps' => $steps,
        ]);
    }

    public function saveStep(Request $request, Attempt $attempt, int $step): RedirectResponse
    {
        $child = Auth::guard('child')->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);
        abort_unless($attempt->status === AttemptStatus::InProgress, 422, 'Esta tentativa já foi submetida.');

        $activityStep = $attempt->assignment->activityVersion->steps()->findOrFail($step);
        $responseType = $activityStep->response_type;

        $mediaAssetId = null;

        $isFileResponse = $responseType->isRecording() || $responseType === ResponseType::Drawing;

        if ($isFileResponse) {
            $request->validate(['file' => ['required', 'file', 'max:'.self::MAX_RECORDING_KB]]);
            $mediaAssetId = $responseType === ResponseType::Drawing
                ? $this->storeDrawing($request, $attempt)
                : $this->storeRecording($request, $attempt, $responseType);
            $value = null;
        } else {
            $data = $request->validate(['value' => ['nullable']]);
            $value = $data['value'] ?? null;
        }

        $isCorrect = null;
        if ($responseType->isAutoScorable()) {
            $correct = $activityStep->response_config['correct'] ?? null;
            $isCorrect = $correct !== null && $value == $correct;
        }

        StepResponse::updateOrCreate(
            ['attempt_id' => $attempt->id, 'activity_step_id' => $activityStep->id],
            [
                'value' => $isFileResponse ? null : $value,
                'media_asset_id' => $mediaAssetId,
                'is_correct' => $isCorrect,
                'answered_at' => now(),
            ],
        );

        return back();
    }

    public function submit(Request $request, Attempt $attempt, RewardService $rewards): RedirectResponse
    {
        $child = Auth::guard('child')->user();
        abort_unless($attempt->assignment->child_profile_id === $child->id, 403);

        // Idempotent: a resend of the same submission key is a no-op, so a
        // dropped response on the client never produces a duplicate submission.
        $key = $request->string('submission_key')->value() ?: (string) $attempt->id;

        if ($attempt->status !== AttemptStatus::InProgress) {
            return redirect()->route('child.home')->with('status', 'Já submetido.');
        }

        DB::transaction(function () use ($attempt, $key) {
            $attempt->update([
                'status' => AttemptStatus::Submitted,
                'submitted_at' => now(),
                'submission_key' => $key,
            ]);
            $attempt->assignment->update(['status' => AssignmentStatus::Submitted]);
        });

        $attempt = $attempt->fresh();
        $rewards->awardParticipation($attempt);

        $childProfile = $attempt->assignment->childProfile;
        $recipients = $childProfile->assignedProfessionals->merge(
            $childProfile->organization->users()->where('role', 'admin')->get(),
        )->unique('id');
        Notification::send($recipients, new AttemptSubmittedNotification($attempt));

        return redirect()->route('child.home')->with('status', 'Atividade submetida! Bom trabalho.');
    }

    private function storeRecording(Request $request, Attempt $attempt, ResponseType $type): int
    {
        $file = $request->file('file');
        $detectedMime = $file->getMimeType();

        if (! in_array($detectedMime, self::RECORDING_MIME_BY_TYPE[$type->value], true)) {
            throw ValidationException::withMessages(['file' => 'Formato de gravação não suportado. Tenta gravar novamente.']);
        }

        $child = Auth::guard('child')->user();
        $path = $file->store('recordings/'.$child->organization_id.'/'.$child->id, 'local');

        $media = MediaAsset::create([
            'organization_id' => $child->organization_id,
            'uploaded_by_user_id' => null,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $detectedMime,
            'kind' => $type === ResponseType::VoiceRecording ? MediaKind::Audio : MediaKind::Video,
            'size_bytes' => $file->getSize(),
            'status' => 'active',
        ]);

        return $media->id;
    }

    private function storeDrawing(Request $request, Attempt $attempt): int
    {
        $file = $request->file('file');
        $detectedMime = $file->getMimeType();

        if ($detectedMime !== 'image/png') {
            throw ValidationException::withMessages(['file' => 'Formato de desenho não suportado. Tenta guardar novamente.']);
        }

        $child = Auth::guard('child')->user();
        $path = $file->store('drawings/'.$child->organization_id.'/'.$child->id, 'local');

        $media = MediaAsset::create([
            'organization_id' => $child->organization_id,
            'uploaded_by_user_id' => null,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $detectedMime,
            'kind' => MediaKind::Image,
            'size_bytes' => $file->getSize(),
            'status' => 'active',
        ]);

        return $media->id;
    }
}
