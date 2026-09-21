<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('mfa.pending_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/MfaChallenge');
    }

    public function store(Request $request, MfaService $mfa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $userId = $request->session()->get('mfa.pending_user_id');
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            return redirect()->route('login');
        }

        $throttleKey = 'mfa-challenge:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $secret = $mfa->decryptSecret($user->mfa_secret);

        if (! $mfa->verify($secret, $request->string('code'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'code' => 'Código inválido. Tente novamente.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = $request->session()->pull('mfa.remember', false);
        $request->session()->forget('mfa.pending_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(AuthenticatedSessionController::homeRouteFor($user));
    }
}
