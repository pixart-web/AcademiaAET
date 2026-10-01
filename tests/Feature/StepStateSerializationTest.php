<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AET-RC01 finding 7: the child-facing UI inferred "recording submitted"
 * from `step.value !== null` — but a file-based response always persists
 * `value: null` server-side (the actual file lives in a MediaAsset, never
 * in the `value` column), so a returning visit to an already-answered
 * recording/drawing step could never tell it was already answered, and
 * had nothing to play back even if it could. These confirm the contract
 * the frontend fix (StepInput.tsx) depends on: `answered` is independent
 * of `value`, and a previously-submitted recording's URL is exposed.
 */
class StepStateSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recording_steps_answered_flag_is_true_even_though_value_is_null(): void
    {
        Storage::fake('local');

        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::VoiceRecording->value],
        ]);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        $attempt = app(AttemptService::class)->startOrResume($assignment);
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();

        $this->actingAsChild($child)->post(
            route('child.attempts.save-step', [$attempt, $step]),
            ['file' => UploadedFile::fake()->create('gravacao.webm', 10, 'audio/webm')],
        )->assertRedirect();

        $this->actingAsChild($child)->get(route('child.attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('steps.0.answered', true)
                ->where('steps.0.value', null)
                ->where('steps.0.response_media_url', fn ($url) => str_contains($url, '/media/'))
            );
    }

    public function test_a_step_never_answered_exposes_no_response_media_url(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::VoiceRecording->value],
        ]);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        $attempt = app(AttemptService::class)->startOrResume($assignment);

        $this->actingAsChild($child)->get(route('child.attempts.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('steps.0.answered', false)
                ->where('steps.0.response_media_url', null)
            );
    }
}
