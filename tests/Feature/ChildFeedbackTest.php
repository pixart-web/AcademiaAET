<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\ClinicalNote;
use App\Models\Evaluation;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ChildFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmittedAttempt(): Attempt
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
            'status' => 'submitted',
        ]);

        return Attempt::factory()->for($assignment)->create(['status' => 'submitted', 'submitted_at' => now()]);
    }

    public function test_child_sees_neutral_awaiting_state_before_evaluation(): void
    {
        $attempt = $this->makeSubmittedAttempt();
        $child = $attempt->assignment->childProfile;

        $this->actingAs($child, 'child')
            ->get("/crianca/atribuicoes/{$attempt->assignment_id}/feedback")
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('assignment.status', 'submitted')
                ->where('evaluation', null)
                ->etc()
            );
    }

    public function test_child_sees_shared_feedback_after_evaluation(): void
    {
        $attempt = $this->makeSubmittedAttempt();
        $child = $attempt->assignment->childProfile;

        Evaluation::create([
            'attempt_id' => $attempt->id,
            'evaluated_by_user_id' => $attempt->assignment->assigned_by_user_id,
            'shared_feedback' => 'Muito bem! Continua assim.',
            'evaluated_at' => now(),
        ]);
        $attempt->update(['status' => 'reviewed']);
        $attempt->assignment->update(['status' => 'reviewed']);

        $this->actingAs($child, 'child')
            ->get("/crianca/atribuicoes/{$attempt->assignment_id}/feedback")
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('evaluation.shared_feedback', 'Muito bem! Continua assim.')
                ->etc()
            );
    }

    public function test_child_sees_neutral_message_when_no_shared_feedback_was_written(): void
    {
        $attempt = $this->makeSubmittedAttempt();
        $child = $attempt->assignment->childProfile;

        Evaluation::create([
            'attempt_id' => $attempt->id,
            'evaluated_by_user_id' => $attempt->assignment->assigned_by_user_id,
            'shared_feedback' => null,
            'evaluated_at' => now(),
        ]);
        $attempt->update(['status' => 'reviewed']);
        $attempt->assignment->update(['status' => 'reviewed']);

        $response = $this->actingAs($child, 'child')->get("/crianca/atribuicoes/{$attempt->assignment_id}/feedback");

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->where('evaluation.shared_feedback', null)
            ->etc()
        );
    }

    public function test_clinical_note_body_never_reaches_the_child_feedback_response(): void
    {
        $attempt = $this->makeSubmittedAttempt();
        $child = $attempt->assignment->childProfile;
        $pro = $attempt->assignment->assignedBy;

        $note = ClinicalNote::create([
            'child_profile_id' => $child->id,
            'author_user_id' => $pro->id,
            'attempt_id' => $attempt->id,
            'body' => 'SEGREDO_CLINICO_NUNCA_EXPOR',
        ]);

        Evaluation::create([
            'attempt_id' => $attempt->id,
            'evaluated_by_user_id' => $pro->id,
            'shared_feedback' => 'Bom trabalho.',
            'clinical_note_id' => $note->id,
            'evaluated_at' => now(),
        ]);
        $attempt->update(['status' => 'reviewed']);
        $attempt->assignment->update(['status' => 'reviewed']);

        $response = $this->actingAs($child, 'child')->get("/crianca/atribuicoes/{$attempt->assignment_id}/feedback");

        $response->assertDontSee('SEGREDO_CLINICO_NUNCA_EXPOR', false);
    }

    public function test_a_child_cannot_view_another_childs_feedback(): void
    {
        $attempt = $this->makeSubmittedAttempt();
        $otherChild = ChildProfile::factory()->for($attempt->assignment->childProfile->organization)->create();

        $this->actingAs($otherChild, 'child')
            ->get("/crianca/atribuicoes/{$attempt->assignment_id}/feedback")
            ->assertForbidden();
    }
}
