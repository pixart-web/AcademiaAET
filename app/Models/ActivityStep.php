<?php

namespace App\Models;

use App\Enums\ResponseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'activity_version_id', 'position', 'title', 'body',
    'instruction_media_asset_id', 'response_type', 'response_config',
])]
class ActivityStep extends Model
{
    protected function casts(): array
    {
        return [
            'response_type' => ResponseType::class,
            'response_config' => 'array',
        ];
    }

    public function activityVersion(): BelongsTo
    {
        return $this->belongsTo(ActivityVersion::class);
    }

    public function instructionMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'instruction_media_asset_id');
    }

    public function stepResponses(): HasMany
    {
        return $this->hasMany(StepResponse::class);
    }

    /**
     * Absent config means required — a step never silently becomes optional
     * just because nobody set the flag.
     */
    public function isRequired(): bool
    {
        return ($this->response_config['required'] ?? true) === true;
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        $options = $this->response_config['options'] ?? [];

        return array_values(array_filter(array_map('strval', $options), fn ($o) => $o !== ''));
    }

    /**
     * Single choice stores `correct` as a string; multiple choice as an
     * array. Both shapes are normalized here so scoring never has to care
     * which one it's looking at — and a legacy string value for a
     * multiple-choice step (impossible via the current editor, but not
     * impossible in older data) is read the same way.
     *
     * @return array<int, string>
     */
    public function correctAnswers(): array
    {
        $correct = $this->response_config['correct'] ?? null;

        if ($correct === null) {
            return [];
        }

        return array_values(array_map('strval', (array) $correct));
    }

    /**
     * The response_config keys that are safe to send to a child — never
     * `correct`, which is the answer key.
     *
     * @return array<string, mixed>
     */
    public function childSafeResponseConfig(): array
    {
        $config = $this->response_config ?? [];
        unset($config['correct']);

        return $config;
    }

    /**
     * AET-RC01 finding 5: MediaPreview used to receive a bare signed URL
     * and guess image-vs-audio from its file extension — which a signed
     * URL never has. This carries the type explicitly instead, plus the
     * text alternatives a child-facing renderer needs regardless of kind.
     * Shared by AttemptService::serializeSteps() (the real execution) and
     * ActivityController::preview() (the staff-only dry run), so the two
     * can never describe the same media differently.
     *
     * @return array{url: string, kind: string, mime_type: string, alt_text: ?string, transcript: ?string}|null
     */
    public function instructionMediaPayload(): ?array
    {
        $media = $this->instructionMedia;

        if ($media === null) {
            return null;
        }

        return [
            'url' => URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $media->id]),
            'kind' => $media->kind->value,
            'mime_type' => $media->mime_type,
            'alt_text' => $media->alt_text,
            'transcript' => $media->transcript,
        ];
    }
}
