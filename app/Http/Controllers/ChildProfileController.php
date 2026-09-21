<?php

namespace App\Http\Controllers;

use App\Enums\ChildStatus;
use App\Enums\VisualExperience;
use App\Models\ChildProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChildProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChildProfile::class);

        $user = $request->user();
        $query = $user->isAdmin()
            ? $user->organization->childProfiles()
            : $user->assignedChildProfiles();

        $children = $query->orderBy('first_name')->get([
            'child_profiles.id', 'first_name', 'preferred_name', 'birth_date',
            'visual_experience', 'status', 'is_demo',
        ]);

        return Inertia::render('Children/Index', ['children' => $children]);
    }

    public function create(): Response
    {
        $this->authorize('create', ChildProfile::class);

        return Inertia::render('Children/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ChildProfile::class);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'care_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $child = new ChildProfile($data);
        $child->organization_id = $request->user()->organization_id;
        $child->status = ChildStatus::Active;
        $child->visual_experience = VisualExperience::suggestedFor(
            now()->diffInYears($data['birth_date'])
        );
        $child->save();

        return redirect()->route('children.show', $child)->with('status', 'Perfil criado.');
    }

    public function show(Request $request, ChildProfile $child): Response
    {
        $this->authorize('view', $child);

        $child->load([
            'guardianRelationships.user',
            'assignedProfessionals',
            'assignments.activityVersion.activity',
            'assignments.attempts',
        ]);

        return Inertia::render('Children/Show', [
            'child' => $child,
            'canManageClinical' => $request->user()->can('manageClinicalData', $child),
        ]);
    }

    public function edit(Request $request, ChildProfile $child): Response
    {
        $this->authorize('update', $child);

        return Inertia::render('Children/Edit', ['child' => $child]);
    }

    public function update(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('update', $child);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'care_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_column(ChildStatus::cases(), 'value'))],
            'visual_experience' => ['required', Rule::in(array_column(VisualExperience::cases(), 'value'))],
        ]);

        $overridden = $data['visual_experience'] !== VisualExperience::suggestedFor(
            now()->diffInYears($data['birth_date'])
        )->value;

        $child->update([...$data, 'visual_experience_overridden' => $overridden]);

        return redirect()->route('children.show', $child)->with('status', 'Perfil atualizado.');
    }

    public function destroy(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('delete', $child);

        $child->update(['status' => ChildStatus::Archived]);
        $child->delete();

        return redirect()->route('children.index')->with('status', 'Perfil arquivado.');
    }
}
