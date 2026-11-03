<?php

namespace App\Models;

use App\Services\Screening\ScreeningAge;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id', 'conducted_by_user_id', 'institution_name', 'child_name', 'birth_date', 'gender',
    'guardian_name', 'guardian_email', 'guardian_phone', 'screened_on', 'age_months', 'age_block',
    'status', 'consent_confirmed_at', 'completed_at', 'result', 'report_path',
    'report_guardian_sent_at', 'report_clinic_sent_at', 'report_error',
])]
#[Hidden(['report_path'])]
class ScreeningSession extends Model
{
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'screened_on' => 'date',
            'consent_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'report_guardian_sent_at' => 'datetime',
            'report_clinic_sent_at' => 'datetime',
            'result' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by_user_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ScreeningResponse::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /** "4 anos e 3 meses" */
    public function ageLabel(): string
    {
        return ScreeningAge::label($this->age_months);
    }
}
