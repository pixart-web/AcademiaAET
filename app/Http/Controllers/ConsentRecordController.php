<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\ConsentRecord;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Records that consent was given (by an authorized adult, in person or on
 * paper — there is no guardian self-service portal yet) — never writes the
 * legal text itself, only which version of the clinic's own text was in
 * effect. See docs/content-guide.md: legal copy is the clinic's to write.
 */
class ConsentRecordController extends Controller
{
    public function store(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('manageClinicalData', $child);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'text_version' => ['required', 'string', 'max:32'],
        ]);

        $consent = ConsentRecord::create([
            'child_profile_id' => $child->id,
            'granted_by_user_id' => $request->user()->id,
            'type' => $data['type'],
            'text_version' => $data['text_version'],
            'granted_at' => now(),
        ]);

        AuditLogger::log('consent.granted', $consent, ['type' => $data['type'], 'text_version' => $data['text_version']], $child->organization_id);

        return back()->with('status', 'Consentimento registado.');
    }

    public function revoke(Request $request, ChildProfile $child, ConsentRecord $consent): RedirectResponse
    {
        $this->authorize('manageClinicalData', $child);
        abort_unless($consent->child_profile_id === $child->id, 404);

        $consent->update(['revoked_at' => now()]);

        AuditLogger::log('consent.revoked', $consent, [], $child->organization_id);

        return back()->with('status', 'Consentimento revogado.');
    }
}
