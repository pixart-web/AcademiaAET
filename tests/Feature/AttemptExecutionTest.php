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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AttemptExecutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(array $steps): Assignment
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, $steps);
        $versioning->publish($version);

        return Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);
    }

    public function test_progress_is_saved_and_the_attempt_resumes_at_the_first_unanswered_step(): void
    {
        $assignment = $this->makeAssignment([
            ['response_type' => ResponseType::ShortText->value],
            ['response_type' => ResponseType::ShortText->value],
        ]);
        $child = $assignment->childProfile;

        $this->actingAsChild($child)->post("/crianca/atribuicoes/{$assignment->id}/iniciar");
        $attempt = Attempt::where('assignment_id', $assignment->id)->firstOrFail();

        $firstStepId = $attempt->assignment->activityVersion->steps()->orderBy('position')->first()->id;

        $this->actingAsChild($child)
            ->post("/crianca/tentativas/{$attempt->id}/passos/{$firstStepId}", ['value' => 'primeira resposta'])
            ->assertRedirect();

        $this->assertDatabaseHas('step_responses', [
            'attempt_id' => $attempt->id,
            'activity_step_id' => $firstStepId,
        ]);

        // A second "start" (e.g. the child re-opens the app) must reuse the
        // same in-progress attempt, not create a new one that would lose
        // the first answer.
        $this->actingAsChild($child)->post("/crianca/atribuicoes/{$assignment->id}/iniciar");
        $this->assertSame(1, Attempt::where('assignment_id', $assignment->id)->count());

        $this->actingAsChild($child)
            ->get("/crianca/tentativas/{$attempt->id}")
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('steps.0.answered', true)
                ->where('steps.1.answered', false)
                ->etc()
            );
    }

    public function test_wrong_file_type_for_a_voice_recording_step_returns_a_clear_validation_error(): void
    {
        $assignment = $this->makeAssignment([
            ['response_type' => ResponseType::VoiceRecording->value],
        ]);
        $child = $assignment->childProfile;

        $this->actingAsChild($child)->post("/crianca/atribuicoes/{$assignment->id}/iniciar");
        $attempt = Attempt::where('assignment_id', $assignment->id)->firstOrFail();
        $stepId = $attempt->assignment->activityVersion->steps()->first()->id;

        $badFile = UploadedFile::fake()->create('nota.txt', 10, 'text/plain');

        $this->actingAsChild($child)
            ->post("/crianca/tentativas/{$attempt->id}/passos/{$stepId}", ['file' => $badFile])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, StepResponse::where('attempt_id', $attempt->id)->whereNotNull('media_asset_id')->count());
    }
}
