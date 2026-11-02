<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceAssociation;
use App\Services\DeviceAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Device-bound child auth for the future mobile apps — no email/password,
 * mirrors ChildSessionController exactly via DeviceAuthService, except the
 * device token is returned in the response body (for the app's own secure
 * storage) instead of an HttpOnly cookie.
 */
class ChildDeviceController extends Controller
{
    public function activate(Request $request, DeviceAuthService $devices): JsonResponse
    {
        $data = $request->validate([
            'device_code' => ['required', 'string'],
            'pin' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        [$device, $deviceToken] = $devices->activate($data['device_code'], $data['pin']);

        $apiToken = $device->childProfile->createToken($data['device_name'], ['child', "device:{$device->id}"])->plainTextToken;

        return response()->json([
            'token' => $apiToken,
            'device_id' => $device->id,
            'device_token' => $deviceToken,
        ]);
    }

    public function unlock(Request $request, DeviceAuthService $devices): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer'],
            'device_token' => ['required', 'string'],
            'pin' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $device = DeviceAssociation::findOrFail($data['device_id']);
        $devices->unlock($device, $data['device_token'], $data['pin']);

        $apiToken = $device->childProfile->createToken($data['device_name'], ['child', "device:{$device->id}"])->plainTextToken;

        return response()->json(['token' => $apiToken]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'ok']);
    }
}
