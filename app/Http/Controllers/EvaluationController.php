<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\ClinicalNote;
use App\Models\Evaluation;
use App\Notifications\EvaluationAvailableNotification;
use App\Services\RewardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $childIds = $user->isAdmin()
            ? $user->organization->childProfiles()->pluck('id')
            : $user->assignedChildProfiles()->pluck('child_profiles.id');

        $pending = Attempt::query()
            ->whereHas('assignment', fn ($q) => $q->whereIn('child_profile_id', $childIds))
            ->where('status', AttemptStatus::Submitted)
            ->with(['assignment.childProfile', 'assignment.activityVersion.activity'])
            ->orderBy('submitted_at')
            ->get();

        return Inertia::render('Evaluations/Index', ['pending' => $pending]);
    }

    public function show(Request $request, Attempt $attempt): Response
    {
        $this->authorize('view', $attempt);

        $attempt->load([
            'assignment.childProfile',
            'assignment.activityVersion.activity',
            'assignment.activityVersion.steps.instructionMedia',
            'stepResponses.media',
            'evaluations.evaluatedBy',
        ]);

        $steps = $attempt->assignment->activityVersion->steps->map(function ($step) use ($attempt) {
            $response = $attempt->stepResponses->firstWhere('activity_step_id', $step->id);

            return [
                'id' => $step->id,
                'title' => $step->title,
                'response_type' => $step->response_type,
                'value' => $response?->value,
                'is_correct' => $response?->is_correct,
                'media_url' => $response?->media
                    ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $response->media->id])
                    : null,
            ];
        });

        return Inertia::render('Evaluations/Show', [
            'attempt' => $attempt,
            'steps' => $steps,
        ]);
    }

    public function store(Request $request, Attempt $attempt, RewardService $rewards): RedirectResponse
    {
        $this->authorize('evaluate', $attempt);

        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'criteria_scores' => ['nullable', 'array'],
            'shared_feedback' => ['nullable', 'string', 'max:3000'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $evaluation = DB::transaction(function () use ($data, $attempt, $request) {
            $note = null;

            if (filled($data['internal_note'] ?? null)) {
                $note = ClinicalNote::create([
                    'child_profile_id' => $attempt->assignment->child_profile_id,
                    'author_user_id' => $request->user()->id,
                    'attempt_id' => $attempt->id,
                    'body' => $data['internal_note'],
                ]);
            }

            $previous = $attempt->evaluations()->latest('id')->first();

            $evaluation = Evaluation::create([
                'attempt_id' => $attempt->id,
                'evaluated_by_user_id' => $request->user()->id,
                'score' => $data['score'] ?? null,
                'criteria_scores' => $data['criteria_scores'] ?? null,
                'shared_feedback' => $data['shared_feedback'] ?? null,
                'clinical_note_id' => $note?->id,
                'supersedes_evaluation_id' => $previous?->id,
                'evaluated_at' => now(),
            ]);

            $attempt->update(['status' => AttemptStatus::Reviewed]);
            $attempt->assignment->update(['status' => AssignmentStatus::Reviewed]);

            return $evaluation;
        });

        $rewards->awardForEvaluation($evaluation);

        $evaluation->attempt->assignment->childProfile
            ->guardianRelationships()->where('status', 'active')->with('user')->get()
            ->each(fn ($relationship) => $relationship->user->notify(new EvaluationAvailableNotification($evaluation)));

        return redirect()->route('evaluations.index')->with('status', 'Avaliação registada.');
    }
}
