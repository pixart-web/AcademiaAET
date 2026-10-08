<?php

namespace Tests\Feature;

use App\Enums\MediaPurpose;
use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\StepResponse;
use App\Models\User;
use App\Services\ActivityVersioningService;
use App\Services\AttemptService;
use App\Services\ChildDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * AET-RC01 finding 8: replacing a recording/drawing left the previous
 * MediaAsset row and its storage file behind, referenced by nothing —
 * and ChildDataService::eraseCompletely() only ever looked at *current*
 * step_responses, so it could never find that orphan either, meaning a
 * "complete" erasure didn't actually remove everything the child had
 * ever produced.
 */
class MediaOwnershipAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function makeAttemptWithRecordingStep(): array
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
        $step = $assignment->activityVersion->steps()->firstOrFail();

        return [$child, $attempt, $step];
    }

    public function test_re_recording_a_step_retires_the_previous_media_asset(): void
    {
        [$child, $attempt, $step] = $this->makeAttemptWithRecordingStep();
        $attempts = app(AttemptService::class);

        $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('primeira.webm', 5, 'audio/webm'));
        $firstMediaId = StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id');
        $firstMedia = MediaAsset::find($firstMediaId);
        Storage::disk('local')->assertExists($firstMedia->path);

        $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('segunda.webm', 5, 'audio/webm'));
        $secondMediaId = StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id');

        $this->assertNotSame($firstMediaId, $secondMediaId);
        // Soft-deleted, not hard-deleted — recoverable, consistent with
        // every other "retire" operation in this codebase.
        $this->assertNull(MediaAsset::find($firstMediaId));
        $this->assertNotNull(MediaAsset::withTrashed()->find($firstMediaId));
        Storage::disk('local')->assertMissing($firstMedia->path);

        // The new one is untouched.
        $this->assertNotNull(MediaAsset::find($secondMediaId));
    }

    public function test_clearing_an_optional_recording_step_also_retires_the_previous_media(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        Storage::fake('local');

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::VoiceRecording->value, 'response_config' => ['required' => false]],
        ]);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);
        $attempt = app(AttemptService::class)->startOrResume($assignment);
        $step = $assignment->activityVersion->steps()->firstOrFail();
        $attempts = app(AttemptService::class);

        $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('gravacao.webm', 5, 'audio/webm'));
        $mediaId = StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id');

        $attempts->saveStep($attempt, $step, $child, null, null);

        $this->assertNull(StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id'));
        $this->assertNull(MediaAsset::find($mediaId));
    }

    public function test_an_invalid_replacement_upload_never_touches_the_previous_response(): void
    {
        [$child, $attempt, $step] = $this->makeAttemptWithRecordingStep();
        $attempts = app(AttemptService::class);

        $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('boa.webm', 5, 'audio/webm'));
        $goodMediaId = StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id');

        try {
            // A .txt masquerading as the recording — rejected by the real
            // (finfo-detected) mime check in storeRecording().
            $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('falsa.webm', 5, 'text/plain'));
            $this->fail('Expected a ValidationException for the wrong mime type.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame($goodMediaId, StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id'));
        $this->assertNotNull(MediaAsset::find($goodMediaId));
    }

    public function test_erasure_removes_a_replaced_recording_that_no_step_response_points_to_anymore(): void
    {
        [$child, $attempt, $step] = $this->makeAttemptWithRecordingStep();
        $attempts = app(AttemptService::class);
        $org = $child->organization;
        $admin = User::factory()->admin()->for($org)->create();

        $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('primeira.webm', 5, 'audio/webm'));
        $orphanedMediaId = StepResponse::where('attempt_id', $attempt->id)->value('media_asset_id');
        $orphaned = MediaAsset::find($orphanedMediaId);

        $attempts->saveStep($attempt, $step, $child, null, UploadedFile::fake()->create('segunda.webm', 5, 'audio/webm'));
        // The first media is already soft-deleted at this point (the
        // retirement above) — simulating the harder case this finding
        // calls out: a row that predates this fix, already orphaned and
        // owned but not referenced by anything current. withTrashed() so
        // it's reachable the way old data genuinely would be.
        $this->assertNotNull(MediaAsset::withTrashed()->find($orphanedMediaId));

        app(ChildDataService::class)->eraseCompletely($child->fresh());

        $this->assertNull(MediaAsset::withTrashed()->find($orphanedMediaId));
        Storage::disk('local')->assertMissing($orphaned->path);
    }

    public function test_erasure_never_touches_another_childs_media_or_shared_instructional_content(): void
    {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $childA = ChildProfile::factory()->for($org)->create();
        $childB = ChildProfile::factory()->for($org)->create();

        $childBsMedia = MediaAsset::factory()->clinicalResponse()->for($org)->create(['owner_child_profile_id' => $childB->id]);
        Storage::disk('local')->put($childBsMedia->path, 'x');
        $instructionalMedia = MediaAsset::factory()->for($org)->create(['purpose' => MediaPurpose::Instructional]);
        Storage::disk('local')->put($instructionalMedia->path, 'x');

        app(ChildDataService::class)->eraseCompletely($childA);

        $this->assertNotNull(MediaAsset::find($childBsMedia->id));
        Storage::disk('local')->assertExists($childBsMedia->path);
        $this->assertNotNull(MediaAsset::find($instructionalMedia->id));
        Storage::disk('local')->assertExists($instructionalMedia->path);
    }
}
