<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * AET-RC01 finding 5: the editor never offered any way to pick instruction
 * media at all (the field existed in the schema and was silently dropped
 * on every save), instruction_media_asset_id only validated a global
 * exists() (any org, any purpose), and archiving a piece of media 404'd
 * the instructions of every activity version that already referenced it,
 * even ones already assigned to a child.
 */
class ActivityMediaInstructionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_step_can_be_created_with_instruction_media_via_http(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $media = MediaAsset::factory()->for($org)->create(['kind' => 'image']);

        $this->actingAs($pro)->post(route('activities.store'), [
            'title' => 'Atividade com imagem',
            'steps' => [[
                'response_type' => ResponseType::ShortText->value,
                'instruction_media_asset_id' => $media->id,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_steps', ['instruction_media_asset_id' => $media->id]);
    }

    public function test_media_from_another_organization_cannot_be_used_as_instruction_media(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $otherOrgMedia = MediaAsset::factory()->for($otherOrg)->create();

        $this->actingAs($pro)->post(route('activities.store'), [
            'title' => 'Atividade maliciosa',
            'steps' => [[
                'response_type' => ResponseType::ShortText->value,
                'instruction_media_asset_id' => $otherOrgMedia->id,
            ]],
        ])->assertSessionHasErrors('steps.0.instruction_media_asset_id');
    }

    public function test_reopening_the_editor_does_not_lose_the_media_association(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $media = MediaAsset::factory()->for($org)->create(['kind' => 'audio']);
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::ShortText->value, 'instruction_media_asset_id' => $media->id],
        ]);

        $this->actingAs($pro)->get(route('activities.edit', $activity))
            ->assertInertia(fn ($page) => $page->where('activity.current_version.steps.0.instruction_media_asset_id', $media->id));

        // Saving again with the same association (as the real form would)
        // must not drop it — the original bug this guards against wasn't
        // in the read path, it was that the editor had no field for it at
        // all and so never sent it back on save.
        $this->actingAs($pro)->put(route('activities.update', $activity), [
            'title' => 'Atividade',
            'steps' => [[
                'response_type' => ResponseType::ShortText->value,
                'instruction_media_asset_id' => $media->id,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_steps', ['instruction_media_asset_id' => $media->id]);
    }

    public function test_the_association_survives_duplication_and_version_forking(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $media = MediaAsset::factory()->for($org)->create(['kind' => 'video']);
        $activity = Activity::factory()->for($org)->create(['title' => 'Atividade', 'created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::ShortText->value, 'instruction_media_asset_id' => $media->id],
        ]);
        $versioning->publish($version);

        $this->actingAs($pro)->post(route('activities.duplicate', $activity))->assertRedirect();
        $copy = Activity::where('title', 'Atividade (cópia)')->firstOrFail();
        $this->assertSame($media->id, $copy->currentVersion->steps->first()->instruction_media_asset_id);

        // Editing the (now-published, assigned-or-not) original forks a
        // new version — the association must carry over to the fork too.
        Assignment::factory()->create([
            'child_profile_id' => ChildProfile::factory()->for($org)->create()->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        $this->actingAs($pro)->put(route('activities.update', $activity), [
            'title' => 'Atividade editada',
            'steps' => [[
                'response_type' => ResponseType::ShortText->value,
                'instruction_media_asset_id' => $media->id,
            ]],
        ]);

        $forked = $activity->fresh()->currentVersion;
        $this->assertNotSame($version->id, $forked->id);
        $this->assertSame($media->id, $forked->steps->first()->instruction_media_asset_id);
    }

    public function test_archiving_instruction_media_preserves_it_for_a_child_already_assigned(): void
    {
        Storage::fake('local');

        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $media = MediaAsset::factory()->for($org)->create(['kind' => 'image', 'uploaded_by_user_id' => $pro->id]);
        Storage::disk('local')->put($media->path, 'fake-image-bytes');

        $versioning = app(ActivityVersioningService::class);
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::CompletionConfirmation->value, 'instruction_media_asset_id' => $media->id],
        ]);
        $versioning->publish($version);

        Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        $this->actingAs($pro)->delete(route('media.destroy', $media))->assertRedirect();
        $this->assertSame('archived', $media->fresh()->status);

        // MediaStreamController tries the 'web' guard before 'child' — the
        // staff actingAs() above left it resolved to $pro, which would
        // otherwise shadow the child login below entirely.
        Auth::guard('web')->logout();

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $media->id]);
        $this->actingAsChild($child)->get($signedUrl)->assertOk();

        // But it's no longer offered for a NEW activity — that's what
        // "archived" is supposed to mean going forward.
        $this->assertDatabaseMissing('media_assets', ['id' => $media->id, 'status' => 'active']);
        $this->actingAs($pro, 'web')->post(route('activities.store'), [
            'title' => 'Nova atividade',
            'steps' => [[
                'response_type' => ResponseType::ShortText->value,
                'instruction_media_asset_id' => $media->id,
            ]],
        ])->assertSessionHasErrors('steps.0.instruction_media_asset_id');
    }

    public function test_a_clinical_response_still_cannot_be_streamed_just_because_it_is_archived_or_not(): void
    {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $outsider = User::factory()->for($org)->create();
        $recording = MediaAsset::factory()->clinicalResponse()->for($org)->create(['status' => 'archived']);
        Storage::disk('local')->put($recording->path, 'x');

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $recording->id]);

        // Archived AND not their case — must fail for both reasons, not
        // accidentally pass because the "assigned instruction media"
        // active-status exception in MediaStreamController applies to
        // something it shouldn't.
        $this->actingAs($outsider)->get($signedUrl)->assertForbidden();
    }
}
