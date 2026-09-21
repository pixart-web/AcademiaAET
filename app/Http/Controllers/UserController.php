<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = $request->user()->organization->users()
            ->whereIn('role', [UserRole::Admin, UserRole::Professional])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'mfa_enabled', 'disabled_at', 'created_at']);

        return Inertia::render('Staff/Index', ['users' => $users]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Staff/Create');
    }

    public function store(Request $request, UserInvitationService $invitations): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,professional'],
        ]);

        $invitations->invite(
            $request->user()->organization,
            $data['name'],
            $data['email'],
            UserRole::from($data['role']),
        );

        return redirect()->route('staff.index')->with('status', 'Convite enviado.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Staff/Edit', ['staffMember' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:admin,professional'],
        ]);

        $user->update($data);

        return back()->with('status', 'Conta atualizada.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->update(['disabled_at' => now()]);

        return back()->with('status', 'Conta desativada.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $user->update(['disabled_at' => null]);

        return back()->with('status', 'Conta reativada.');
    }

    public function resendActivation(Request $request, User $user, UserInvitationService $invitations): RedirectResponse
    {
        $this->authorize('update', $user);

        $invitations->resendActivation($user);

        return back()->with('status', 'Convite reenviado.');
    }
}
