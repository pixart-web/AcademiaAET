<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Records who did what to which record, for the small set of actions
 * sensitive enough to need a trail (device access, evaluations, account
 * state changes) — never logs response content, clinical text, or tokens.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function log(string $action, ?Model $subject = null, array $metadata = [], ?int $organizationId = null, ?int $userId = null): AuditEvent
    {
        $user = $userId !== null ? null : auth('web')->user();

        return AuditEvent::create([
            'organization_id' => $organizationId ?? $subject?->organization_id ?? $user?->organization_id,
            'user_id' => $userId ?? $user?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->id,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
        ]);
    }
}
