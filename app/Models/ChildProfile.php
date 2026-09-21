<?php

namespace App\Models;

use App\Enums\ChildStatus;
use App\Enums\VisualExperience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id', 'first_name', 'preferred_name', 'birth_date',
    'visual_experience', 'visual_experience_overridden', 'status', 'care_notes', 'is_demo',
])]
class ChildProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'visual_experience' => VisualExperience::class,
            'visual_experience_overridden' => 'boolean',
            'status' => ChildStatus::class,
            'is_demo' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function guardianRelationships(): HasMany
    {
        return $this->hasMany(GuardianRelationship::class);
    }

    public function professionalAssignments(): HasMany
    {
        return $this->hasMany(ProfessionalAssignment::class);
    }

    public function assignedProfessionals(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'professional_assignments')
            ->wherePivot('active', true)
            ->withTimestamps();
    }

    public function consentRecords(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function deviceAssociations(): HasMany
    {
        return $this->hasMany(DeviceAssociation::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function rewardEvents(): HasMany
    {
        return $this->hasMany(RewardEvent::class);
    }

    public function ageInYears(): int
    {
        return (int) $this->birth_date->diffInYears(now());
    }

    public function suggestedVisualExperience(): VisualExperience
    {
        return VisualExperience::suggestedFor($this->ageInYears());
    }
}
