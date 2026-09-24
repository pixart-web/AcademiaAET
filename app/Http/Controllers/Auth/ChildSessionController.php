<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DeviceAssociation;
use App\Services\DeviceAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ChildSessionController extends Controller
{
    private const COOKIE_NAME = 'child_device';

    public function create(Request $request): Response
    {
        $device = $this->resolveDeviceFromCookie($request);

        return Inertia::render('Auth/ChildEntry', [
            'deviceActivated' => $device !== null,
        ]);
    }

    public function activate(Request $request, DeviceAuthService $devices): RedirectResponse
    {
        $data = $request->validate([
            'device_code' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ]);

        [$device, $token] = $devices->activate($data['device_code'], $data['pin']);

        $this->loginChild($request, $device, $token);

        return redirect()->route('child.home');
    }

    public function unlock(Request $request, DeviceAuthService $devices): RedirectResponse
    {
        $device = $this->resolveDeviceFromCookie($request);

        if (! $device) {
            return redirect()->route('child.login');
        }

        $data = $request->validate(['pin' => ['required', 'string']]);
        $cookieToken = $this->tokenFromCookie($request);

        try {
            $devices->unlock($device, $cookieToken, $data['pin']);
        } catch (ValidationException $e) {
            throw $e;
        }

        $this->loginChild($request, $device, $cookieToken);

        return redirect()->route('child.home');
    }

    /**
     * Ends the child's session without touching the device pairing, so staff
     * can hand the tablet to another child (who unlocks with their own PIN)
     * without ever exposing the previous child's data or the professional portal.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('child')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('child.login');
    }

    private function loginChild(Request $request, DeviceAssociation $device, string $deviceToken): void
    {
        // Defense in depth: a browser session must never carry both a staff and
        // a child login at once.
        Auth::guard('web')->logout();

        Auth::guard('child')->login($device->childProfile);
        $request->session()->regenerate();

        Cookie::queue(Cookie::make(
            self::COOKIE_NAME,
            $device->id.'.'.$deviceToken,
            60 * 24 * 7,
            httpOnly: true,
            secure: app()->isProduction(),
            sameSite: 'lax',
        ));
    }

    private function resolveDeviceFromCookie(Request $request): ?DeviceAssociation
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if (! $raw || ! str_contains($raw, '.')) {
            return null;
        }

        [$id, $token] = explode('.', $raw, 2);
        $device = DeviceAssociation::find($id);

        if (! $device || ! $device->isActivated() || ! $device->checkDeviceToken($token)) {
            return null;
        }

        return $device;
    }

    private function tokenFromCookie(Request $request): string
    {
        [, $token] = explode('.', $request->cookie(self::COOKIE_NAME), 2);

        return $token;
    }
}
