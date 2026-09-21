<?php

namespace App\Http\Controllers;

use App\Models\ActivityStep;
use App\Models\MediaAsset;
use App\Models\StepResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    /**
     * Reached only via a short-lived signed URL (see routes/web.php), on top of
     * an authorization check — a guessable ID is never sufficient on its own.
     */
    public function __invoke(Request $request, MediaAsset $media): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            abort_unless($user->organization_id === $media->organization_id, 403);
        } elseif (Auth::guard('child')->check()) {
            $child = Auth::guard('child')->user();
            abort_unless($this->childMayAccess($child, $media), 403);
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
