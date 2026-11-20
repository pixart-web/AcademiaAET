<?php

namespace App\Models;

use App\Enums\ScreeningItemKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'age_block', 'kind', 'position', 'prompt', 'target_word', 'phoneme',
    'options', 'expected_answer', 'image_media_asset_id', 'is_demo',
])]
class ScreeningItem extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => ScreeningItemKind::class,
            'options' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_asset_id');
    }

    public function phonemeNorm(): ?array
    {
        return $this->phoneme ? config("screening.phonemes.{$this->phoneme}") : null;
    }
}
