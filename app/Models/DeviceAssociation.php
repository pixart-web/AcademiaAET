<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable(['child_profile_id', 'device_identifier', 'status', 'expires_at', 'created_by_user_id', 'revoked_at', 'revoked_by_user_id'])]
#[Hidden(['pin_hash', 'device_token_hash'])]
class DeviceAssociation extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'session_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function childProfile(): BelongsTo
    {
        return $this->belongsTo(ChildProfile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * Gates the one-time activation code + PIN, before the device is
     * paired — never used once `activated_at` is set, which is exactly
     * what makes activation one-time-use: a second activate() call with
     * the same code always fails here, regardless of whether the code or
     * PIN themselves are still "correct".
     */
    public function isActivationUsable(): bool
    {
        if ($this->status !== 'active' || $this->activated_at !== null) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        if ($this->locked_until && $this->locked_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Gates day-to-day PIN unlock on an already-paired device — a wholly
     * separate clock from the activation code's own short deadline (see
     * migration 2026_09_30_202909), so a device in daily use doesn't stop
     * working the moment the original activation code would have expired.
     */
    public function isSessionUsable(): bool
    {
        if ($this->status !== 'active' || $this->activated_at === null) {
            return false;
        }

        if ($this->session_expires_at !== null && $this->session_expires_at->isPast()) {
            return false;
        }

        if ($this->locked_until && $this->locked_until->isFuture()) {
            return false;
        }

        return true;
    }

    public function setPin(string $pin): void
    {
        $this->pin_hash = Hash::make($pin);
    }

    public function checkPin(string $pin): bool
    {
        return Hash::check($pin, $this->pin_hash);
    }

    public function isActivated(): bool
    {
        return $this->activated_at !== null;
    }

    public function activateWithToken(string $deviceToken): void
    {
        $this->device_token_hash = hash('sha256', $deviceToken);
        $this->activated_at = now();
    }

    public function checkDeviceToken(string $deviceToken): bool
    {
        return $this->device_token_hash !== null
            && hash_equals($this->device_token_hash, hash('sha256', $deviceToken));
    }

    /**
     * AET-RC01 finding 2: the single place that decides whether a claimed
     * device association still authorizes an already-authenticated child —
     * used identically by the web session guard (EnsureChildDeviceIsActive),
     * the API token guard (EnsureApiPrincipal) and MediaStreamController, so
     * revoking or expiring one device is enforced on every surface the same
     * way, not just the ones someone remembered to check. A missing or
     * unresolvable $deviceAssociationId (a legacy session/token minted
     * before this device binding existed) is always treated as invalid —
     * never trusted by default.
     */
    /**
     * Sanctum abilities are the only per-token metadata available without a
     * schema change to personal_access_tokens — "device:42" is set once, at
     * token creation (Api\ChildDeviceController), and never trusted for
     * anything beyond finding which row to re-validate against the database.
     */
    public static function idFromTokenAbilities(array $abilities): ?int
    {
        foreach ($abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, 'device:')) {
                return (int) substr($ability, 7);
            }
        }

        return null;
    }

    public static function resolveActiveFor(ChildProfile $child, ?int $deviceAssociationId): ?self
    {
        if ($deviceAssociationId === null) {
            return null;
        }

        $device = static::find($deviceAssociationId);

        if (! $device || $device->child_profile_id !== $child->id || ! $device->isSessionUsable()) {
            return null;
        }

        return $device;
    }
}
