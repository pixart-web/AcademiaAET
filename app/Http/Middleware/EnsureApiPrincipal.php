<?php

namespace App\Http\Middleware;

use App\Models\ChildProfile;
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

        return $next($request);
    }
}
