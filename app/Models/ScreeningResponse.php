<?php

namespace App\Models;

use App\Enums\ScreeningOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['screening_session_id', 'screening_item_id', 'outcome', 'points', 'produced_sound', 'answered_at'])]
class ScreeningResponse extends Model
{
    protected function casts(): array
    {
        return [
            'outcome' => ScreeningOutcome::class,
            'answered_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScreeningSession::class, 'screening_session_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ScreeningItem::class, 'screening_item_id');
    }
}
