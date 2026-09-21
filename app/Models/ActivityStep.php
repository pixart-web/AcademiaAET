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
}
