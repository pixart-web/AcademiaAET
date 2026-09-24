<?php

namespace App\Http\Controllers;

use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceAssociationController extends Controller
{
    /**
     * Creates a one-time activation code + PIN. Both are shown to staff exactly
     * once in the redirect flash — never persisted anywhere in plain text.
     */
    public function store(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('manageClinicalData', $child);

        $deviceIdentifier = Str::upper(Str::random(10));
        $pin = (string) random_int(1000, 9999);

        $device = new DeviceAssociation([
            'device_identifier' => $deviceIdentifier,
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $request->user()->id;
        $device->setPin($pin);
        $device->save();

        AuditLogger::log('device.activation_issued', $child, ['device_association_id' => $device->id]);

        return back()->with([
            'status' => 'Acesso gerado. Introduza este código e PIN no dispositivo da criança — só são mostrados agora.',
            'newDeviceCode' => $deviceIdentifier,
            'newDevicePin' => $pin,
        ]);
    }

    public function revoke(Request $request, ChildProfile $child, DeviceAssociation $device): RedirectResponse
    {
        $this->authorize('revoke', $device);
        abort_unless($device->child_profile_id === $child->id, 404);

        $device->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()->id,
        ]);

        AuditLogger::log('device.revoked', $child, ['device_association_id' => $device->id]);

        return back()->with('status', 'Acesso do dispositivo revogado.');
    }
}
