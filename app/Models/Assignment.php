<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'child_profile_id', 'activity_version_id', 'assigned_by_user_id',
    'instructions_override', 'due_at', 'max_attempts', 'status', 'cancelled_at',
])]
class Assignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'due_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function childProfile(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class);
    }

    public function activityVersion(): BelongsTo
    {
        return $this->belongsTo(ActivityVersion::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && in_array($this->status, [AssignmentStatus::Assigned, AssignmentStatus::Started], true);
    }

    public function nextAttemptNumber(): int
    {
        return ($this->attempts()->max('attempt_number') ?? 0) + 1;
    }

    public function hasAttemptsRemaining(): bool
    {
        if ($this->max_attempts === null) {
            return true;
        }

        return $this->attempts()->count() < $this->max_attempts;
    }
}
