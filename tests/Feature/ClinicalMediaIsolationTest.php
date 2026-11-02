<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\StepResponse;
use App\Models\User;
use App\Services\ActivityVersioningService;
use App\Services\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * AET-RC01 finding 1: MediaAssetController::index() listed every active
 * media row in the organization, including children's own recordings and
 * drawings (created via AttemptService::storeRecording()/storeDrawing()
 * with no distinguishing field from a professional's didactic upload), and
 * MediaStreamController authorized a staff principal by organization
 * membership alone — a therapist with no case assignment to a child could
 * stream that child's clinical recordings just by knowing (or guessing)
 * the media id, since a valid signature says nothing about who the
 * requester is.
 */
class ClinicalMediaIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeChildWithRecording(): array
    {
        Storage::fake('local');

        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $assignedPro = User::factory()->for($org)->create();
        $unassignedPro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        ProfessionalAssignment::create([
            'child_profile_id' => $child->id,
            'user_id' => $assignedPro->id,
            'assigned_by_user_id' => $admin->id,
            'active' => true,
            'started_at' => now(),
        ]);

        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $assignedPro->id]);
        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $assignedPro, 'Atividade', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $assignedPro->id,
        ]);

        $this->actingAsChild($child)->post("/crianca/atribuicoes/{$assignment->id}/iniciar");
        $attempt = Attempt::where('assignment_id', $assignment->id)->firstOrFail();
        $step = $attempt->assignment->activityVersion->steps()->firstOrFail();

        $this->actingAsChild($child)->post(
            "/crianca/tentativas/{$attempt->id}/passos/{$step->id}",
            ['value' => 'uma resposta de texto, não um ficheiro — trocamos o passo abaixo por um de gravação'],
        );

        // Swap in a real clinical recording the same way AttemptService
        // does it, so the test doesn't depend on browser MediaRecorder.
        $recording = MediaAsset::factory()->clinicalResponse()->for($org)->create([
            'owner_child_profile_id' => $child->id,
            'kind' => 'audio',
            'mime_type' => 'audio/webm',
        ]);
        Storage::disk('local')->put($recording->path, 'fake-audio-bytes');
        StepResponse::where('attempt_id', $attempt->id)->where('activity_step_id', $step->id)
            ->update(['media_asset_id' => $recording->id, 'value' => null]);

        // Every auth:child request above (via the real HTTP calls, not
        // just actingAsChild()'s own setUser()) runs Laravel's own
        // Authenticate middleware, which calls Auth::shouldUse('child') on
        // success — silently making 'child' the default guard for the
        // rest of this test method. Every test using this helper then
        // calls actingAs($staffUser, 'web') explicitly rather than relying
        // on actingAs()'s default (guard: null), which would otherwise
        // resolve to whatever 'child' left behind and log the staff user
        // into the wrong guard. forgetGuards() alone doesn't fix this —
        // the default *driver name* is mutated config state, not just a
        // cached guard instance.
        $this->app['auth']->forgetGuards();

        return [$org, $admin, $assignedPro, $unassignedPro, $child, $recording];
    }

    public function test_unassigned_professional_cannot_stream_another_professionals_childs_recording(): void
    {
        [, , , $unassignedPro, , $recording] = $this->makeChildWithRecording();

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $recording->id]);

        $this->actingAs($unassignedPro, 'web')->get($signedUrl)->assertForbidden();
    }

    public function test_assigned_professional_can_stream_the_recording(): void
    {
        [, , $assignedPro, , , $recording] = $this->makeChildWithRecording();

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $recording->id]);

        $this->actingAs($assignedPro, 'web')->get($signedUrl)->assertOk();
    }

    public function test_admin_can_stream_the_recording_without_a_case_assignment(): void
    {
        [, $admin, , , , $recording] = $this->makeChildWithRecording();

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $recording->id]);

        $this->actingAs($admin, 'web')->get($signedUrl)->assertOk();
    }

    public function test_a_professional_from_another_organization_is_rejected(): void
    {
        [, , , , , $recording] = $this->makeChildWithRecording();
        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->for($otherOrg)->create();

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $recording->id]);

        $this->actingAs($outsider, 'web')->get($signedUrl)->assertForbidden();
    }

    public function test_the_clinical_recording_never_appears_in_the_didactic_media_library(): void
    {
        [, , $assignedPro] = $this->makeChildWithRecording();

        $response = $this->actingAs($assignedPro, 'web')->get(route('media.index'));

        $response->assertInertia(fn ($page) => $page->where('media.total', 0));
    }

    public function test_instructional_content_remains_listed_and_streamable_as_before(): void
    {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $media = MediaAsset::factory()->for($org)->create(['kind' => 'image']);
        Storage::disk('local')->put($media->path, 'fake-image-bytes');

        $this->actingAs($pro)->get(route('media.index'))
            ->assertInertia(fn ($page) => $page->where('media.total', 1));

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $media->id]);
        $this->actingAs($pro)->get($signedUrl)->assertOk();
    }

    public function test_a_clinical_recording_cannot_be_assigned_as_instruction_media_via_a_manually_crafted_id(): void
    {
        [, , $assignedPro, , , $recording] = $this->makeChildWithRecording();

        $this->actingAs($assignedPro, 'web')->post(route('activities.store'), [
            'title' => 'Atividade maliciosa',
            'steps' => [[
                'response_type' => ResponseType::ShortText->value,
                'instruction_media_asset_id' => $recording->id,
            ]],
        ])->assertSessionHasErrors('steps.0.instruction_media_asset_id');
    }
}
