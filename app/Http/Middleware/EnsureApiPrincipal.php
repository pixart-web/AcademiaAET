<?php

namespace App\Http\Middleware;

use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A Sanctum token authenticates *some* model; this pins an API route group to
 * exactly one (child vs. staff), since the two carry very different
 * authorization rules and a child token must never reach a staff endpoint
 * or vice versa.
 */
class EnsureApiPrincipal
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();
        $expected = $type === 'child' ? ChildProfile::class : User::class;

        abort_unless($user instanceof $expected, 403, 'Token não autorizado para este recurso.');

        if ($expected === User::class && ! $user->isActive()) {
            abort(403, 'Conta desativada.');
        }

        if ($expected === ChildProfile::class) {
            // AET-RC01 finding 2: a token survived its device being revoked
            // or expiring — re-checked on every request, same as the web
            // session guard (EnsureChildDeviceIsActive). A token minted
            // before the "device:{id}" ability existed has no way to prove
            // which device it belongs to, so it is never trusted by
            // default (idFromTokenAbilities() returns null for it, and
            // resolveActiveFor() treats null as invalid).
            $token = $user->currentAccessToken();
            $deviceId = $token ? DeviceAssociation::idFromTokenAbilities($token->abilities ?? []) : null;

            if (! $user->isActive() || DeviceAssociation::resolveActiveFor($user, $deviceId) === null) {
                $token?->delete();
                abort(401, 'Este acesso já não está disponível.');
            }
        }

        return $next($request);
    }
}
