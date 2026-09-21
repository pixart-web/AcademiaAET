<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaSettingsController extends Controller
{
    public function edit(Request $request, MfaService $mfa): Response
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('mfa.setup_secret');

        return Inertia::render('Settings/Mfa', [
            'mfaEnabled' => $user->mfa_enabled,
            'setupQrCodeDataUri' => $pendingSecret ? $mfa->qrCodeDataUri($user, $pendingSecret) : null,
        ]);
    }

    public function enable(Request $request, MfaService $mfa): RedirectResponse
    {
        $user = $request->user();

        // Step 1: no code yet — generate a secret, show the QR, wait for the
        // user to prove they scanned it before we ever turn MFA on.
        if (! $request->filled('code')) {
            $secret = $mfa->generateSecret();
            $request->session()->put('mfa.setup_secret', $secret);

            return back();
        }

        $secret = $request->session()->get('mfa.setup_secret');

        if (! $secret || ! $mfa->verify($secret, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Código inválido.']);
        }

        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => $mfa->encryptSecret($secret),
        ]);

        $request->session()->forget('mfa.setup_secret');

        return redirect()->route('mfa.edit')->with('status', 'Autenticação em dois passos ativada.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->user()->update(['mfa_enabled' => false, 'mfa_secret' => null]);

        return redirect()->route('mfa.edit')->with('status', 'Autenticação em dois passos desativada.');
    }
}
