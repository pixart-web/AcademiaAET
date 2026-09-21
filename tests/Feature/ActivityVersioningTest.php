<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_an_unassigned_draft_version_updates_it_in_place(): void
    {
        $org = Organization::factory()->create();
        $author = User::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $author->id]);

        $versioning = app(ActivityVersioningService::class);
        $v1 = $versioning->createInitialVersion($activity, $author, 'Título original', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);

        $v2 = $versioning->updateOrFork($v1, $author, 'Título editado', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);

        $this->assertSame($v1->id, $v2->id);
        $this->assertSame('Título editado', $v2->fresh()->title);
    }

    public function test_editing_a_version_already_assigned_to_a_child_forks_a_new_version(): void
    {
        $org = Organization::factory()->create();
        $author = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $author->id]);

        $versioning = app(ActivityVersioningService::class);
        $v1 = $versioning->createInitialVersion($activity, $author, 'Versão 1', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);
        $versioning->publish($v1);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $v1->id,
            'assigned_by_user_id' => $author->id,
        ]);

        $v2 = $versioning->updateOrFork($v1->fresh(), $author, 'Versão 2', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);

        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame('Versão 1', $v1->fresh()->title);
        $this->assertSame($v2->id, $activity->fresh()->current_version_id);

        // The already-issued assignment must keep pointing at the original,
        // stable version — a later edit to the activity cannot rewrite history.
        $this->assertSame($v1->id, $assignment->fresh()->activity_version_id);
    }
}
