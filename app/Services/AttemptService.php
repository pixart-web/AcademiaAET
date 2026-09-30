<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\MediaKind;
use App\Enums\MediaPurpose;
use App\Enums\ResponseType;
use App\Models\ActivityStep;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\MediaAsset;
use App\Models\StepResponse;
use App\Notifications\AttemptSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * The one execution engine behind both the web portal (AttemptController)
 * and the mobile API (Api\AttemptController) — persistence, idempotent
 * submission and authorization-relevant state transitions live here exactly
 * once, so the two front doors can never drift apart.
 */
class AttemptService
{
    private const RECORDING_MIME_BY_TYPE = [
        'voice_recording' => ['audio/webm', 'audio/mp4', 'audio/mpeg', 'audio/wav', 'audio/ogg'],
        'video_recording' => ['video/webm', 'video/mp4'],
    ];

    public function __construct(
        private readonly RewardService $rewards,
        private readonly StepResponseValidator $validator,
    ) {}

    /**
     * Resuming an in-progress attempt is always allowed, regardless of
     * max_attempts — the limit only gates *creating a new* attempt. Checking
     * for an in-progress attempt happens first and inside the same locked
     * transaction as the creation, so two concurrent requests can never both
     * decide "no attempt exists yet" and both create one (the DB's partial
     * unique index on attempts(assignment_id) WHERE status='in_progress' is
     * the second line of defense if that locking is ever bypassed).
     */
    public function startOrResume(Assignment $assignment): Attempt
    {
        abort_if($assignment->status === AssignmentStatus::Cancelled, 422, 'Esta atribuição foi cancelada.');

        return DB::transaction(function () use ($assignment) {
            $locked = Assignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            abort_if($locked->status === AssignmentStatus::Cancelled, 422, 'Esta atribuição foi cancelada.');

            $attempt = $locked->attempts()->where('status', 'in_progress')->first();
            if ($attempt) {
                return $attempt;
            }

            abort_unless($locked->hasAttemptsRemaining(), 422, 'Sem mais tentativas disponíveis.');

            $locked->update(['status' => AssignmentStatus::Started]);

            return $locked->attempts()->create([
                'attempt_number' => $locked->nextAttemptNumber(),
                'status' => AttemptStatus::InProgress,
                'started_at' => now(),
            ]);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serializeSteps(Attempt $attempt): array
    {
        $attempt->loadMissing(['assignment.activityVersion.steps.instructionMedia', 'stepResponses']);

        return $attempt->assignment->activityVersion->steps->map(function (ActivityStep $step) use ($attempt) {
            $response = $attempt->stepResponses->firstWhere('activity_step_id', $step->id);

            return [
                'id' => $step->id,
                'position' => $step->position,
                'title' => $step->title,
                'body' => $step->body,
                'response_type' => $step->response_type,
                // Never the raw config — it may hold `correct`, the answer key.
                'response_config' => $step->childSafeResponseConfig(),
                'required' => $step->isRequired(),
                'instruction_media' => $step->instructionMediaPayload(),
                'answered' => $response !== null,
                'value' => $response?->value,
                'response_media_url' => $response?->media_asset_id
                    ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $response->media_asset_id])
                    : null,
            ];
        })->all();
    }

    public function saveStep(Attempt $attempt, ActivityStep $step, ChildProfile $child, mixed $value, ?UploadedFile $file): StepResponse
    {
        abort_unless($attempt->status === AttemptStatus::InProgress, 422, 'Esta tentativa já foi submetida.');
        abort_if($attempt->assignment->status === AssignmentStatus::Cancelled, 422, 'Esta atribuição foi cancelada.');

        $responseType = $step->response_type;
        $isFileResponse = $responseType->isRecording() || $responseType === ResponseType::Drawing;

        if ($isFileResponse) {
            if ($file === null) {
                $this->abortIfFileRequired($step);

                return StepResponse::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'activity_step_id' => $step->id],
                    ['value' => null, 'media_asset_id' => null, 'is_correct' => null, 'answered_at' => null],
                );
            }

            $mediaAssetId = $responseType === ResponseType::Drawing
                ? $this->storeDrawing($file, $child)
                : $this->storeRecording($file, $child, $responseType);

            return StepResponse::updateOrCreate(
                ['attempt_id' => $attempt->id, 'activity_step_id' => $step->id],
                ['value' => null, 'media_asset_id' => $mediaAssetId, 'is_correct' => null, 'answered_at' => now()],
            );
        }

        $result = $this->validator->validateValue($step, $value);

        return StepResponse::updateOrCreate(
            ['attempt_id' => $attempt->id, 'activity_step_id' => $step->id],
            [
                'value' => $result['value'],
                'media_asset_id' => null,
                'is_correct' => $result['is_correct'],
                'answered_at' => $result['value'] === null ? null : now(),
            ],
        );
    }

    private function abortIfFileRequired(ActivityStep $step): void
    {
        if ($step->isRequired()) {
            throw ValidationException::withMessages(['file' => 'Ficheiro em falta.']);
        }
    }

    private function assertAllRequiredStepsAnswered(Attempt $attempt): void
    {
        $attempt->loadMissing(['assignment.activityVersion.steps', 'stepResponses']);

        $answeredStepIds = $attempt->stepResponses
            ->filter(fn (StepResponse $r) => $r->answered_at !== null)
            ->pluck('activity_step_id');

        $missing = $attempt->assignment->activityVersion->steps
            ->filter(fn (ActivityStep $step) => $step->isRequired() && ! $answeredStepIds->contains($step->id));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'steps' => 'Ainda há passos obrigatórios por responder.',
            ]);
        }
    }

    public function submit(Attempt $attempt, ?string $submissionKey = null): Attempt
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            return $attempt;
        }

        abort_if($attempt->assignment->status === AssignmentStatus::Cancelled, 422, 'Esta atribuição foi cancelada.');

        $key = $submissionKey ?: (string) $attempt->id;

        $alreadySubmitted = DB::transaction(function () use ($attempt, $key) {
            // Re-check inside the lock: two concurrent submits of the same
            // attempt must not both pass the InProgress check above and both
            // try to transition it — the second one, after the first
            // commits, sees Submitted already and becomes a no-op.
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== AttemptStatus::InProgress) {
                return true;
            }

            if ($locked->assignment->status === AssignmentStatus::Cancelled) {
                abort(422, 'Esta atribuição foi cancelada.');
            }

            $this->assertAllRequiredStepsAnswered($locked);

            $locked->update([
                'status' => AttemptStatus::Submitted,
                'submitted_at' => now(),
                'submission_key' => $key,
            ]);
            $locked->assignment->update(['status' => AssignmentStatus::Submitted]);

            return false;
        });

        if ($alreadySubmitted) {
            return $attempt->fresh();
        }

        $attempt = $attempt->fresh();
        $this->rewards->awardParticipation($attempt);

        $childProfile = $attempt->assignment->childProfile;
        $recipients = $childProfile->assignedProfessionals->merge(
            $childProfile->organization->users()->where('role', 'admin')->get(),
        )->unique('id');
        Notification::send($recipients, new AttemptSubmittedNotification($attempt));

        return $attempt;
    }

    private function storeRecording(UploadedFile $file, ChildProfile $child, ResponseType $type): int
    {
        $detectedMime = $file->getMimeType();

        if (! in_array($detectedMime, self::RECORDING_MIME_BY_TYPE[$type->value], true)) {
            throw ValidationException::withMessages(['file' => 'Formato de gravação não suportado. Tenta gravar novamente.']);
        }

        $path = $file->store('recordings/'.$child->organization_id.'/'.$child->id, 'local');

        return MediaAsset::create([
            'organization_id' => $child->organization_id,
            'uploaded_by_user_id' => null,
            'purpose' => MediaPurpose::ClinicalResponse,
            'owner_child_profile_id' => $child->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $detectedMime,
            'kind' => $type === ResponseType::VoiceRecording ? MediaKind::Audio : MediaKind::Video,
            'size_bytes' => $file->getSize(),
            'status' => 'active',
        ])->id;
    }

    private function storeDrawing(UploadedFile $file, ChildProfile $child): int
    {
        $detectedMime = $file->getMimeType();

        if ($detectedMime !== 'image/png') {
            throw ValidationException::withMessages(['file' => 'Formato de desenho não suportado. Tenta guardar novamente.']);
        }

        $path = $file->store('drawings/'.$child->organization_id.'/'.$child->id, 'local');

        return MediaAsset::create([
            'organization_id' => $child->organization_id,
            'uploaded_by_user_id' => null,
            'purpose' => MediaPurpose::ClinicalResponse,
            'owner_child_profile_id' => $child->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $detectedMime,
            'kind' => MediaKind::Image,
            'size_bytes' => $file->getSize(),
            'status' => 'active',
        ])->id;
    }
}
