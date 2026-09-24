<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Staff (admin/professional) token auth for the future companion apps.
 * Reuses the exact same credential + MFA verification as the web portal's
 * LoginRequest/MfaChallengeController — only the "how the session is kept"
 * differs (bearer token here, cookie there).
 */
class AuthController extends Controller
{
    public function login(Request $request, MfaService $mfa): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'mfa_code' => ['nullable', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiadas tentativas. Tente novamente em '.RateLimiter::availableIn($throttleKey).' segundos.',
            ]);
        }

        $provider = Auth::createUserProvider('users');
        $user = $provider->retrieveByCredentials(['email' => $data['email'], 'password' => $data['password']]);

        if (! $user || ! $provider->validateCredentials($user, $data) || ! $user->isActive()) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        if ($user->mfa_enabled) {
            if (blank($data['mfa_code'] ?? null)) {
                return response()->json(['mfa_required' => true], 401);
            }

            if (! $mfa->verify($mfa->decryptSecret($user->mfa_secret), $data['mfa_code'])) {
                throw ValidationException::withMessages(['mfa_code' => 'Código inválido.']);
            }
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken($data['device_name'], ['staff'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['status' => 'ok']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }
}
