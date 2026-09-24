<?php

namespace App\Http\Controllers;

use App\Models\ActivityStep;
use App\Models\ChildProfile;
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
            abort_unless($this->childMayAccess($principal, $media), 403);
        } else {
            abort(401);
        }

        abort_unless($media->status === 'active', 404);

        return Storage::disk($media->disk)->response($media->path, null, [
            'Cache-Control' => 'private, max-age=60, no-store',
        ]);
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
