<?php

namespace App\Http\Controllers;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Services\ActivityVersioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $activities = $request->user()->organization->activities()
            ->with('currentVersion')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Activities/Index', ['activities' => $activities]);
    }

    public function create(): Response
    {
        $this->authorize('create', Activity::class);

        return Inertia::render('Activities/Edit', ['activity' => null, 'responseTypes' => $this->responseTypeOptions()]);
    }

    public function store(Request $request, ActivityVersioningService $versioning): RedirectResponse
    {
        $this->authorize('create', Activity::class);

        $data = $this->validateActivity($request);

        $activity = Activity::create([
            'organization_id' => $request->user()->organization_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'area' => $data['area'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'status' => 'draft',
            'created_by_user_id' => $request->user()->id,
            'is_demo' => $data['is_demo'] ?? false,
        ]);

        $versioning->createInitialVersion(
            $activity,
            $request->user(),
            $data['title'],
            $data['instructions'] ?? null,
            $data['evaluation_criteria'] ?? null,
            $data['steps'],
        );

        return redirect()->route('activities.edit', $activity)->with('status', 'Atividade criada em rascunho.');
    }

    public function edit(Request $request, Activity $activity): Response
    {
        $this->authorize('update', $activity);

        $activity->load('currentVersion.steps.instructionMedia');

        return Inertia::render('Activities/Edit', [
            'activity' => $activity,
            'responseTypes' => $this->responseTypeOptions(),
        ]);
    }

    public function update(Request $request, Activity $activity, ActivityVersioningService $versioning): RedirectResponse
    {
        $this->authorize('update', $activity);

        $data = $this->validateActivity($request);

        $activity->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'area' => $data['area'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        $versioning->updateOrFork(
            $activity->currentVersion,
            $request->user(),
            $data['title'],
            $data['instructions'] ?? null,
            $data['evaluation_criteria'] ?? null,
            $data['steps'],
        );

        return redirect()->route('activities.edit', $activity)->with('status', 'Atividade guardada.');
    }

    public function publish(Request $request, Activity $activity, ActivityVersioningService $versioning): RedirectResponse
    {
        $this->authorize('publish', $activity);

        $versioning->publish($activity->currentVersion);

        return back()->with('status', 'Atividade publicada.');
    }

    public function archive(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('archive', $activity);

        $activity->update(['status' => 'archived']);

        return back()->with('status', 'Atividade arquivada.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return redirect()->route('activities.index')->with('status', 'Atividade eliminada.');
    }

    /**
     * Duplicates the activity as a brand-new draft (its own Activity +
     * ActivityVersion + steps) — never a new version of the original, so
     * the source keeps its own history untouched.
     */
    public function duplicate(Request $request, Activity $activity, ActivityVersioningService $versioning): RedirectResponse
    {
        $this->authorize('create', Activity::class);
        $this->authorize('view', $activity);

        $activity->loadMissing('currentVersion.steps');

        $copy = DB::transaction(function () use ($activity, $request, $versioning) {
            $newActivity = Activity::create([
                'organization_id' => $activity->organization_id,
                'title' => $activity->title.' (cópia)',
                'description' => $activity->description,
                'category' => $activity->category,
                'area' => $activity->area,
                'difficulty' => $activity->difficulty,
                'status' => 'draft',
                'created_by_user_id' => $request->user()->id,
                'is_demo' => false,
            ]);

            $steps = $activity->currentVersion->steps->map(fn ($step) => [
                'title' => $step->title,
                'body' => $step->body,
                'instruction_media_asset_id' => $step->instruction_media_asset_id,
                'response_type' => $step->response_type->value,
                'response_config' => $step->response_config,
            ])->all();

            $versioning->createInitialVersion(
                $newActivity,
                $request->user(),
                $newActivity->title,
                $activity->currentVersion->instructions,
                $activity->currentVersion->evaluation_criteria,
                $steps,
            );

            return $newActivity;
        });

        return redirect()->route('activities.edit', $copy)->with('status', 'Atividade duplicada como novo rascunho.');
    }

    /**
     * Read-only preview of the current version's steps as each child shell
     * would render them — nothing here is persisted (no Attempt is ever
     * created), so a professional can check an activity before publishing it.
     */
    public function preview(Request $request, Activity $activity): Response
    {
        $this->authorize('view', $activity);

        $activity->loadMissing('currentVersion.steps.instructionMedia');

        $steps = $activity->currentVersion
            ? $activity->currentVersion->steps->map(fn ($step) => [
                'id' => $step->id,
                'position' => $step->position,
                'title' => $step->title,
                'body' => $step->body,
                'response_type' => $step->response_type,
                'response_config' => $step->response_config,
                'instruction_media_url' => $step->instructionMedia
                    ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $step->instructionMedia->id])
                    : null,
                'answered' => false,
                'value' => null,
            ])->all()
            : [];

        return Inertia::render('Activities/Preview', [
            'activity' => ['id' => $activity->id, 'title' => $activity->title],
            'steps' => $steps,
        ]);
    }

    private function validateActivity(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:64'],
            'area' => ['nullable', 'string', 'max:64'],
            'difficulty' => ['nullable', 'string', 'max:16'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'evaluation_criteria' => ['nullable', 'string', 'max:5000'],
            'is_demo' => ['sometimes', 'boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.title' => ['nullable', 'string', 'max:255'],
            'steps.*.body' => ['nullable', 'string', 'max:5000'],
            'steps.*.instruction_media_asset_id' => ['nullable', 'exists:media_assets,id'],
            'steps.*.response_type' => ['required', Rule::in(array_column(ResponseType::cases(), 'value'))],
            'steps.*.response_config' => ['nullable', 'array'],
        ]);
    }

    private function responseTypeOptions(): array
    {
        return collect(ResponseType::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->name,
        ])->all();
    }
}
