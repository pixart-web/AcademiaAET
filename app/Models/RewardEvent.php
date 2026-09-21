<?php

namespace App\Models;

use App\Enums\RewardType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['child_profile_id', 'attempt_id', 'type', 'points', 'dedupe_key', 'awarded_at'])]
class RewardEvent extends Model
{
    protected function casts(): array
    {
        return [
            'type' => RewardType::class,
            'awarded_at' => 'datetime',
        ];
    }

    public function childProfile(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public static function dedupeKeyFor(int $attemptId, RewardType $type): string
    {
        return "attempt:{$attemptId}:reward:{$type->value}";
    }
}
