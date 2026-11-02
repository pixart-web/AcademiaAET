<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\StepResponse;
use App\Models\User;
use App\Services\ActivityVersioningService;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AET-RC01 finding 6: response_config['correct'] was sent verbatim to the
 * child (the answer key), no type answered specific server-side validation,
 * submission accepted incomplete attempts, and multiple-choice scoring used
 * a positional `==` comparison that failed for a correct answer given in a
 * different order.
 */
class StepResponseValidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttempt(array $steps): Attempt
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, $steps);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        return app(AttemptService::class)->startOrResume($assignment);
    }

    public function test_child_payload_never_includes_the_correct_answer(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::SingleChoice->value, 'response_config' => ['options' => ['a', 'b'], 'correct' => 'b']],
        ]);

        $this->actingAsChild($attempt->assignment->childProfile)
            ->get(route('child.attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('steps.0.response_config.options', ['a', 'b'])
                ->missing('steps.0.response_config.correct')
            );
    }

    public function test_single_choice_rejects_an_option_not_in_the_configured_set(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::SingleChoice->value, 'response_config' => ['options' => ['a', 'b'], 'correct' => 'a']],
        ]);
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();

        $this->actingAsChild($attempt->assignment->childProfile)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => 'z'])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('step_responses', ['attempt_id' => $attempt->id]);
    }

    public function test_multiple_choice_is_scored_correctly_regardless_of_selection_order(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::MultipleChoice->value, 'response_config' => ['options' => ['a', 'b', 'c'], 'correct' => ['a', 'c']]],
        ]);
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();

        $this->actingAsChild($attempt->assignment->childProfile)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => ['c', 'a']])
            ->assertRedirect();

        $this->assertTrue(StepResponse::where('attempt_id', $attempt->id)->first()->is_correct);
    }

    public function test_multiple_choice_rejects_duplicate_or_invented_options(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::MultipleChoice->value, 'response_config' => ['options' => ['a', 'b'], 'correct' => ['a']]],
        ]);
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();
        $child = $attempt->assignment->childProfile;

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => ['a', 'a']])
            ->assertSessionHasErrors('value');

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => ['a', 'invented']])
            ->assertSessionHasErrors('value');
    }

    public function test_short_text_rejects_empty_when_required_and_enforces_max_length(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::ShortText->value, 'response_config' => ['max_length' => 10]],
        ]);
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();
        $child = $attempt->assignment->childProfile;

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => ''])
            ->assertSessionHasErrors('value');

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => 'demasiado longo para caber'])
            ->assertSessionHasErrors('value');
    }

    public function test_completion_confirmation_requires_a_true_boolean(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();
        $child = $attempt->assignment->childProfile;

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => 'yes please'])
            ->assertSessionHasErrors('value');

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $step]), ['value' => true])
            ->assertRedirect();

        $this->assertNotNull(StepResponse::where('attempt_id', $attempt->id)->first()->answered_at);
    }

    public function test_submit_is_rejected_while_a_required_step_is_unanswered(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::ShortText->value],
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);
        $child = $attempt->assignment->childProfile;

        $this->actingAsChild($child)
            ->post(route('child.attempts.submit', $attempt))
            ->assertSessionHasErrors('steps');

        $this->assertSame('in_progress', $attempt->fresh()->status->value);
    }

    public function test_submit_succeeds_once_all_required_steps_are_answered_but_optional_ones_can_stay_empty(): void
    {
        $attempt = $this->makeAttempt([
            ['response_type' => ResponseType::ShortText->value],
            ['response_type' => ResponseType::ShortText->value, 'response_config' => ['required' => false]],
        ]);
        $child = $attempt->assignment->childProfile;
        $steps = $attempt->assignment->activityVersion->steps;

        $this->actingAsChild($child)
            ->post(route('child.attempts.save-step', [$attempt, $steps[0]]), ['value' => 'resposta']);

        $this->actingAsChild($child)
            ->post(route('child.attempts.submit', $attempt))
            ->assertRedirect(route('child.home'));

        $this->assertSame('submitted', $attempt->fresh()->status->value);
    }
}
