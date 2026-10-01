<?php

namespace App\Models;

use App\Enums\ChildStatus;
use App\Enums\VisualExperience;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * Authenticatable so the "child" session guard (web) and Sanctum tokens (the
 * future mobile apps, see routes/api.php) can both log a child profile in
 * directly after a device+PIN check — children never have a password, only a
 * device-scoped PIN validated against DeviceAssociation before login() is called.
 */
/**
 * care_notes is deliberately NOT #[Hidden] — the child-facing side never
 * serializes this model at all (HandleInertiaRequests and the API's
 * ChildMeController both hand-pick an explicit, separate field list for
 * auth.child), so hiding it here bought no real protection and only broke
 * the staff edit form: Inertia's serialization would silently strip the
 * value, so opening "Editar perfil" for any reason and saving would wipe
 * out any existing care_notes. Found via the same audit that caught the
 * $fillable mass-assignment bugs, not by reading this comment first.
 */
#[Fillable([
    'organization_id', 'first_name', 'preferred_name', 'birth_date',
    'visual_experience', 'visual_experience_overridden', 'status', 'care_notes', 'is_demo',
])]
class ChildProfile extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, SoftDeletes;

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
        // ->age (not diffInYears, whose sign flips with argument order) is the
        // unambiguous way to get a birth date's current age in Carbon 3.
        return $this->birth_date->age;
    }

    public function suggestedVisualExperience(): VisualExperience
    {
        return VisualExperience::suggestedFor($this->ageInYears());
    }

    public function isActive(): bool
    {
        return $this->status === ChildStatus::Active;
    }
}
