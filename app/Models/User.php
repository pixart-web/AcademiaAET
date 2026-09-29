<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'organization_id', 'mfa_enabled', 'mfa_secret', 'disabled_at'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'mfa_enabled' => 'boolean',
            'disabled_at' => 'datetime',
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

    public function assignedChildProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ChildProfile::class, 'professional_assignments')
            ->wherePivot('active', true)
            ->withPivot(['started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isProfessional(): bool
    {
        return $this->role === UserRole::Professional;
    }

    public function isGuardian(): bool
    {
        return $this->role === UserRole::Guardian;
    }

    public function isActive(): bool
    {
        return $this->disabled_at === null;
    }
}
