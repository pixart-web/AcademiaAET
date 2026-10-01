<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * AET-RC01 finding 4: startOrResume() checked the max_attempts limit before
 * looking for an in-progress attempt, so an activity with max_attempts=1
 * could never be resumed after leaving mid-attempt (the limit check aborted
 * before the "is there already an attempt?" check ran). Cancelling an
 * assignment also didn't block saving a step or submitting an
 * already-started attempt.
 */
class AttemptResumeAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(?int $maxAttempts = null): Assignment
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

        return Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
            'max_attempts' => $maxAttempts,
        ]);
    }

    public function test_an_assignment_limited_to_one_attempt_can_still_be_resumed_after_leaving_mid_attempt(): void
    {
        $assignment = $this->makeAssignment(maxAttempts: 1);
        $attempts = app(AttemptService::class);

        $first = $attempts->startOrResume($assignment);
        // Simulating the child leaving and coming back — a second call
        // while nothing has been submitted yet must return the SAME
        // in-progress attempt, not be blocked by the attempts-remaining
        // check (which only gates *creating a new* attempt).
        $resumed = $attempts->startOrResume($assignment->fresh());

        $this->assertSame($first->id, $resumed->id);
        $this->assertSame(1, Attempt::where('assignment_id', $assignment->id)->count());
    }

    public function test_once_the_single_attempt_is_submitted_a_new_one_cannot_be_started(): void
    {
        $assignment = $this->makeAssignment(maxAttempts: 1);
        $attempts = app(AttemptService::class);
        $child = $assignment->childProfile;

        $attempt = $attempts->startOrResume($assignment);
        $step = $assignment->activityVersion->steps()->firstOrFail();
        $attempts->saveStep($attempt, $step, $child, true, null);
        $attempts->submit($attempt);

        $this->expectException(HttpException::class);
        $attempts->startOrResume($assignment->fresh());
    }

    public function test_cancelling_an_assignment_blocks_saving_a_step_on_its_in_progress_attempt(): void
    {
        $assignment = $this->makeAssignment();
        $attempts = app(AttemptService::class);
        $child = $assignment->childProfile;

        $attempt = $attempts->startOrResume($assignment);
        $assignment->update(['status' => AssignmentStatus::Cancelled, 'cancelled_at' => now()]);

        $step = $assignment->activityVersion->steps()->firstOrFail();

        $this->expectException(HttpException::class);
        $attempts->saveStep($attempt->fresh(), $step, $child, true, null);
    }

    public function test_cancelling_an_assignment_blocks_submitting_its_in_progress_attempt(): void
    {
        $assignment = $this->makeAssignment();
        $attempts = app(AttemptService::class);
        $child = $assignment->childProfile;

        $attempt = $attempts->startOrResume($assignment);
        $step = $assignment->activityVersion->steps()->firstOrFail();
        $attempts->saveStep($attempt, $step, $child, true, null);

        $assignment->update(['status' => AssignmentStatus::Cancelled, 'cancelled_at' => now()]);

        $this->expectException(HttpException::class);
        $attempts->submit($attempt->fresh());
    }

    public function test_cancelling_an_assignment_blocks_starting_a_new_attempt(): void
    {
        $assignment = $this->makeAssignment();
        $assignment->update(['status' => AssignmentStatus::Cancelled, 'cancelled_at' => now()]);

        $this->expectException(HttpException::class);
        app(AttemptService::class)->startOrResume($assignment->fresh());
    }
}
