<?php

namespace App\Http\Controllers;

use App\Enums\ChildStatus;
use App\Enums\VisualExperience;
use App\Models\Activity;
use App\Models\ChildProfile;
use App\Services\AuditLogger;
use App\Services\ChildDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            Carbon::parse($data['birth_date'])->age
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
            'deviceAssociations' => fn ($q) => $q->latest(),
            'consentRecords' => fn ($q) => $q->with('grantedBy')->latest('granted_at'),
        ]);

        $child->assignments->each(fn ($assignment) => $assignment->setAttribute('is_overdue', $assignment->isOverdue()));

        $publishableActivities = $request->user()->can('manageClinicalData', $child)
            ? Activity::query()
                ->where('organization_id', $child->organization_id)
                ->where('status', 'published')
                ->orderBy('title')
                ->get(['id', 'title'])
            : [];

        $summary = [
            'total' => $child->assignments->count(),
            // ->status is an AssignmentStatus enum instance, not a string — a
            // loose Collection::where() comparison against a plain string
            // never matches a backed enum, so compare ->value explicitly.
            'completed' => $child->assignments->whereIn('status.value', ['submitted', 'reviewed'])->count(),
            'pending_evaluation' => $child->assignments->where('status.value', 'submitted')->count(),
            'overdue' => $child->assignments->filter->is_overdue->count(),
        ];

        return Inertia::render('Children/Show', [
            'child' => $child,
            'canManageClinical' => $request->user()->can('manageClinicalData', $child),
            'canDelete' => $request->user()->can('delete', $child),
            'publishableActivities' => $publishableActivities,
            'summary' => $summary,
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
            Carbon::parse($data['birth_date'])->age
        )->value;

        $careNotesChanged = $child->care_notes !== ($data['care_notes'] ?? null);

        $child->update([...$data, 'visual_experience_overridden' => $overridden]);

        // Never logs the note text itself — clinical content stays out of the
        // audit trail, only that an edit touching it happened, and when.
        AuditLogger::log('child.profile_updated', $child, ['care_notes_changed' => $careNotesChanged]);

        return redirect()->route('children.show', $child)->with('status', 'Perfil atualizado.');
    }

    public function destroy(Request $request, ChildProfile $child): RedirectResponse
    {
        $this->authorize('delete', $child);

        $child->update(['status' => ChildStatus::Archived]);
        $child->delete();

        AuditLogger::log('child.archived', $child);

        return redirect()->route('children.index')->with('status', 'Perfil arquivado.');
    }

    /**
     * Full data export for a compliance request (subject access) — includes
     * clinical notes, since this is the clinic's own record, not something
     * shown to the child. Downloaded directly, never emailed.
     */
    public function export(Request $request, ChildProfile $child, ChildDataService $data): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('manageClinicalData', $child);

        AuditLogger::log('child.exported', $child);

        $filename = 'academia-aet-'.$child->id.'-'.now()->format('Ymd-His').'.json';

        return response()->json($data->export($child))
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Irreversible. Requires the admin to type the child's own first name as
     * confirmation (checked server-side, not just disabled by the UI).
     */
    public function eraseCompletely(Request $request, ChildProfile $child, ChildDataService $data): RedirectResponse
    {
        $this->authorize('delete', $child);

        $request->validate(['confirm_name' => ['required', 'string']]);

        if (trim($request->string('confirm_name')) !== $child->first_name) {
            return back()->withErrors(['confirm_name' => 'O nome não corresponde. Nada foi eliminado.']);
        }

        AuditLogger::log('child.erased_permanently', $child, ['first_name' => $child->first_name], $child->organization_id);

        $data->eraseCompletely($child);

        return redirect()->route('children.index')->with('status', 'Todos os dados deste perfil foram eliminados permanentemente.');
    }
}
