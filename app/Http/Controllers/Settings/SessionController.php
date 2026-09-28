<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists and revokes this account's own active sessions (database session
 * driver only) — a professional/admin logged in on a lost or shared device
 * can end that session without knowing its password.
 */
class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => date('c', $session->last_activity),
                'is_current' => $session->id === $request->session()->getId(),
            ]);

        return Inertia::render('Settings/Sessions', ['sessions' => $sessions]);
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        abort_if($sessionId === $request->session()->getId(), 422, 'Use "Sair" para terminar a sessão atual.');

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        abort_unless($deleted > 0, 404);

        AuditLogger::log('session.revoked', $request->user(), ['session_id' => $sessionId]);

        return back()->with('status', 'Sessão terminada.');
    }
}
