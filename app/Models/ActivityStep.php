<?php

namespace App\Models;

use App\Enums\ResponseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
