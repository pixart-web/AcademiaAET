<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'attempt_id', 'evaluated_by_user_id', 'score', 'criteria_scores',
    'shared_feedback', 'clinical_note_id', 'supersedes_evaluation_id', 'evaluated_at',
])]
class Evaluation extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'criteria_scores' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }

    public function clinicalNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_evaluation_id');
    }
}
