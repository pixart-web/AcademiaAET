<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\MediaKind;
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

    public function __construct(private readonly RewardService $rewards) {}

    public function startOrResume(Assignment $assignment): Attempt
    {
        abort_unless($assignment->hasAttemptsRemaining(), 422, 'Sem mais tentativas disponíveis.');
        abort_if($assignment->status === AssignmentStatus::Cancelled, 422);

        $attempt = $assignment->attempts()->where('status', 'in_progress')->first();

        if ($attempt) {
            return $attempt;
        }

        return DB::transaction(function () use ($assignment) {
            $assignment->update(['status' => AssignmentStatus::Started]);

            return $assignment->attempts()->create([
                'attempt_number' => $assignment->nextAttemptNumber(),
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
                'response_config' => $step->response_config,
                'instruction_media_url' => $step->instructionMedia
                    ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $step->instructionMedia->id])
                    : null,
                'answered' => $response !== null,
                'value' => $response?->value,
            ];
        })->all();
    }

    public function saveStep(Attempt $attempt, ActivityStep $step, ChildProfile $child, mixed $value, ?UploadedFile $file): StepResponse
    {
        abort_unless($attempt->status === AttemptStatus::InProgress, 422, 'Esta tentativa já foi submetida.');

        $responseType = $step->response_type;
        $isFileResponse = $responseType->isRecording() || $responseType === ResponseType::Drawing;
        $mediaAssetId = null;

        if ($isFileResponse) {
            abort_unless($file !== null, 422, 'Ficheiro em falta.');
            $mediaAssetId = $responseType === ResponseType::Drawing
                ? $this->storeDrawing($file, $child)
                : $this->storeRecording($file, $child, $responseType);
            $value = null;
        }

        $isCorrect = null;
        if ($responseType->isAutoScorable()) {
            $correct = $step->response_config['correct'] ?? null;
            $isCorrect = $correct !== null && $value == $correct;
        }

        return StepResponse::updateOrCreate(
            ['attempt_id' => $attempt->id, 'activity_step_id' => $step->id],
            [
                'value' => $isFileResponse ? null : $value,
                'media_asset_id' => $mediaAssetId,
                'is_correct' => $isCorrect,
                'answered_at' => now(),
            ],
        );
    }

    public function submit(Attempt $attempt, ?string $submissionKey = null): Attempt
    {
        if ($attempt->status !== AttemptStatus::InProgress) {
            return $attempt;
        }

        $key = $submissionKey ?: (string) $attempt->id;

        DB::transaction(function () use ($attempt, $key) {
            $attempt->update([
                'status' => AttemptStatus::Submitted,
                'submitted_at' => now(),
                'submission_key' => $key,
            ]);
            $attempt->assignment->update(['status' => AssignmentStatus::Submitted]);
        });

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
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $detectedMime,
            'kind' => MediaKind::Image,
            'size_bytes' => $file->getSize(),
            'status' => 'active',
        ])->id;
    }
}
