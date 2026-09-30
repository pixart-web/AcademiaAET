<?php

namespace App\Http\Controllers;

use App\Models\ActivityStep;
use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\MediaAsset;
use App\Models\StepResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    /**
     * Reached only via a short-lived signed URL (see routes/web.php), on top of
     * an authorization check — a guessable ID is never sufficient on its own.
     * Also accepts a Sanctum bearer token (mobile apps), on top of the two
     * session guards used by the web portal.
     */
    public function __invoke(Request $request, MediaAsset $media): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $principal = Auth::guard('web')->user()
            ?? Auth::guard('child')->user()
            ?? Auth::guard('sanctum')->user();

        if ($principal instanceof User) {
            abort_unless($principal->organization_id === $media->organization_id, 403);
        } elseif ($principal instanceof ChildProfile) {
            abort_unless($principal->isActive(), 403);
            abort_unless($this->childDeviceIsActive($principal, $request), 403);
            abort_unless($this->childMayAccess($principal, $media), 403);
        } else {
            abort(401);
        }

        abort_unless($media->status === 'active', 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Cache-Control' => 'private, max-age=60, no-store',
        ]);
    }

    /**
     * AET-RC01 finding 2: a signed URL alone never substitutes for
     * authorization, and a revoked/expired device was the one gap a signed
     * URL genuinely could bypass — this route has no guard middleware at
     * all (it manually tries all three), so the check has to live here
     * directly rather than in route middleware.
     */
    private function childDeviceIsActive(ChildProfile $child, Request $request): bool
    {
        $deviceId = $request->hasSession() && $request->session()->has('child_device_association_id')
            ? $request->session()->get('child_device_association_id')
            : DeviceAssociation::idFromTokenAbilities($child->currentAccessToken()?->abilities ?? []);

        return DeviceAssociation::resolveActiveFor($child, $deviceId) !== null;
    }

    private function childMayAccess($child, MediaAsset $media): bool
    {
        if ($media->organization_id !== $child->organization_id) {
            return false;
        }

        $isInstructionMedia = ActivityStep::query()
            ->where('instruction_media_asset_id', $media->id)
            ->whereHas('activityVersion.assignments', fn ($q) => $q->where('child_profile_id', $child->id))
            ->exists();

        $isOwnRecording = StepResponse::query()
            ->where('media_asset_id', $media->id)
            ->whereHas('attempt.assignment', fn ($q) => $q->where('child_profile_id', $child->id))
            ->exists();

        return $isInstructionMedia || $isOwnRecording;
    }
}
