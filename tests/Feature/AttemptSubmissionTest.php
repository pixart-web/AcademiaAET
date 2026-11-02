<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\RewardEvent;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttemptSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(): Assignment
    {
        $org = Organization::factory()->create();
        $author = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $author->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $author, 'Atividade de teste', null, null, [
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);
        $versioning->publish($version);

        return Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $author->id,
        ]);
    }

    public function test_resubmitting_an_already_submitted_attempt_does_not_duplicate_rewards(): void
    {
        $assignment = $this->makeAssignment();
        $child = $assignment->childProfile;

        $this->actingAsChild($child)->post("/crianca/atribuicoes/{$assignment->id}/iniciar");
        $attempt = Attempt::where('assignment_id', $assignment->id)->firstOrFail();
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();

        $this->actingAsChild($child)
            ->post("/crianca/tentativas/{$attempt->id}/passos/{$step->id}", ['value' => true])
            ->assertRedirect();

        $this->actingAsChild($child)->post("/crianca/tentativas/{$attempt->id}/submeter")->assertRedirect();
        $this->actingAsChild($child)->post("/crianca/tentativas/{$attempt->id}/submeter")->assertRedirect();

        $this->assertSame(1, RewardEvent::where('attempt_id', $attempt->id)->count());
    }

    public function test_a_child_cannot_access_another_childs_attempt(): void
    {
        $assignment = $this->makeAssignment();
        $this->actingAsChild($assignment->childProfile)->post("/crianca/atribuicoes/{$assignment->id}/iniciar");
        $attempt = Attempt::where('assignment_id', $assignment->id)->firstOrFail();

        $otherChild = ChildProfile::factory()->for($assignment->childProfile->organization)->create();

        $this->actingAsChild($otherChild)->get("/crianca/tentativas/{$attempt->id}")->assertForbidden();
    }
}
