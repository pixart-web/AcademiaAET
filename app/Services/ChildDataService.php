<?php

namespace App\Services;

use App\Models\ChildProfile;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-facing export and erasure for one child's full record — for a
 * clinic's own compliance process (data subject access / right to erasure
 * requests), never surfaced to the child. Export intentionally includes
 * clinical_notes (this is the clinic's own copy of its record, not a
 * child-facing contract); erasure permanently removes it.
 */
class ChildDataService
{
    /**
     * @return array<string, mixed>
     */
    public function export(ChildProfile $child): array
    {
        $child->loadMissing([
            'guardianRelationships.user:id,name,email',
            'professionalAssignments.professional:id,name,email',
            'consentRecords.grantedBy:id,name',
            'deviceAssociations',
            'clinicalNotes.author:id,name',
            'rewardEvents',
            'assignments.activityVersion.activity:id,title',
            'assignments.attempts.stepResponses',
            'assignments.attempts.evaluations.evaluatedBy:id,name',
        ]);

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => $child->only(['id', 'first_name', 'preferred_name', 'birth_date', 'visual_experience', 'status', 'care_notes', 'created_at']),
            'guardians' => $child->guardianRelationships->map(fn ($r) => [
                'name' => $r->user->name, 'email' => $r->user->email, 'relationship_type' => $r->relationship_type, 'status' => $r->status,
            ]),
            'professionals' => $child->professionalAssignments->map(fn ($a) => [
                'name' => $a->professional->name, 'active' => $a->active, 'started_at' => $a->started_at, 'ended_at' => $a->ended_at,
            ]),
            'consent_records' => $child->consentRecords->map(fn ($c) => [
                'type' => $c->type, 'text_version' => $c->text_version, 'granted_by' => $c->grantedBy->name,
                'granted_at' => $c->granted_at, 'revoked_at' => $c->revoked_at,
            ]),
            'device_associations' => $child->deviceAssociations->map->only(['device_identifier', 'status', 'activated_at', 'expires_at', 'session_expires_at', 'last_used_at', 'revoked_at']),
            'clinical_notes' => $child->clinicalNotes->map(fn ($n) => [
                'author' => $n->author->name, 'body' => $n->body, 'created_at' => $n->created_at,
            ]),
            'reward_events' => $child->rewardEvents->map->only(['type', 'points', 'awarded_at']),
            'assignments' => $child->assignments->map(fn ($assignment) => [
                'activity_title' => $assignment->activityVersion->activity->title,
                'status' => $assignment->status,
                'due_at' => $assignment->due_at,
                'attempts' => $assignment->attempts->map(fn ($attempt) => [
                    'attempt_number' => $attempt->attempt_number,
                    'status' => $attempt->status,
                    'submitted_at' => $attempt->submitted_at,
                    'responses' => $attempt->stepResponses->map->only(['activity_step_id', 'value', 'is_correct', 'answered_at']),
                    'evaluations' => $attempt->evaluations->map(fn ($e) => [
                        'evaluated_by' => $e->evaluatedBy->name, 'score' => $e->score,
                        'shared_feedback' => $e->shared_feedback, 'evaluated_at' => $e->evaluated_at,
                    ]),
                ]),
            ]),
        ];
    }

    /**
     * Permanently removes the child's own recordings/drawings from storage,
     * then force-deletes the profile — Postgres cascades the rest (assignments,
     * attempts, step_responses, evaluations, clinical_notes, consent_records,
     * device_associations, professional_assignments, guardian_relationships;
     * see the cascadeOnDelete() constraints in database/migrations).
     */
    public function eraseCompletely(ChildProfile $child): void
    {
        DB::transaction(function () use ($child) {
            $mediaIds = MediaAsset::query()
                ->whereIn('id', function ($query) use ($child) {
                    $query->select('media_asset_id')
                        ->from('step_responses')
                        ->whereIn('attempt_id', function ($sub) use ($child) {
                            $sub->select('attempts.id')
                                ->from('attempts')
                                ->join('assignments', 'assignments.id', '=', 'attempts.assignment_id')
                                ->where('assignments.child_profile_id', $child->id);
                        })
                        ->whereNotNull('media_asset_id');
                })
                ->get(['id', 'disk', 'path']);

            foreach ($mediaIds as $media) {
                Storage::disk($media->disk)->delete($media->path);
            }
            MediaAsset::whereIn('id', $mediaIds->pluck('id'))->delete();

            $child->forceDelete();
        });
    }
}
