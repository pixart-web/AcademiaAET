<?php

namespace App\Http\Middleware;

use App\Models\DeviceAssociation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AET-RC01 finding 2: revoking a device association, or it expiring, used
 * to have no effect on a child session already established from it — the
 * session cookie alone kept authorizing every request indefinitely. This
 * re-checks the actual device on every request, the same way
 * EnsureAccountIsActive does for staff accounts.
 */
class EnsureChildDeviceIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $child = Auth::guard('child')->user();

        if ($child === null) {
            return $next($request);
        }

        $deviceId = $request->session()->get('child_device_association_id');
        $device = DeviceAssociation::resolveActiveFor($child, $deviceId);

        if ($device === null || ! $child->isActive()) {
            Auth::guard('child')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('child.login')->withErrors([
                'pin' => 'Este acesso já não está disponível. Pede a um adulto para o desbloquear outra vez.',
            ]);
        }

        return $next($request);
    }
}
