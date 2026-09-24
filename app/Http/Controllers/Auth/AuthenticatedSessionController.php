<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        if ($user->mfa_enabled) {
            $request->session()->put('mfa.pending_user_id', $user->id);
            $request->session()->put('mfa.remember', $request->boolean('remember'));

            return redirect()->route('mfa.challenge');
        }

        Auth::guard('child')->logout();
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        AuditLogger::log('staff.login', $user, [], userId: $user->id);

        return redirect()->intended($this->homeRouteFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public static function homeRouteFor($user): string
    {
        return match ($user->role) {
            UserRole::Admin, UserRole::Professional => route('dashboard', absolute: false),
            UserRole::Guardian => route('guardian.home', absolute: false),
        };
    }
}
