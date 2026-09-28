<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Attempt;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ActivityPreviewAndDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function makeActivity(User $author, Organization $org): Activity
    {
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $author->id, 'title' => 'Original']);
        $versioning = app(ActivityVersioningService::class);
        $versioning->createInitialVersion($activity, $author, 'Original', 'instruções', null, [
            ['title' => 'Passo 1', 'response_type' => ResponseType::ShortText->value],
        ]);

        return $activity->fresh();
    }

    public function test_preview_shows_steps_without_creating_an_attempt(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $activity = $this->makeActivity($pro, $org);

        $this->actingAs($pro)
            ->get(route('activities.preview', $activity))
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('activity.title', 'Original')
                ->where('steps.0.title', 'Passo 1')
                ->where('steps.0.answered', false)
                ->etc()
            );

        $this->assertSame(0, Attempt::count());
    }

    public function test_duplicate_creates_a_new_draft_activity_leaving_the_original_untouched(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $activity = $this->makeActivity($pro, $org);
        $versioning = app(ActivityVersioningService::class);
        $versioning->publish($activity->currentVersion);
        $activity->refresh();

        $this->actingAs($pro)
            ->post(route('activities.duplicate', $activity))
            ->assertRedirect();

        $this->assertDatabaseHas('activities', ['title' => 'Original (cópia)', 'status' => 'draft']);
        $this->assertSame('published', $activity->fresh()->status->value);

        $copy = Activity::where('title', 'Original (cópia)')->firstOrFail();
        $this->assertNotSame($activity->id, $copy->id);
        $this->assertSame(1, $copy->currentVersion->steps()->count());
    }

    public function test_unassigned_professional_cannot_preview_or_duplicate_another_organizations_activity(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $outsider = User::factory()->for($otherOrg)->create();
        $activity = $this->makeActivity($pro, $org);

        $this->actingAs($outsider)->get(route('activities.preview', $activity))->assertForbidden();
        $this->actingAs($outsider)->post(route('activities.duplicate', $activity))->assertForbidden();
    }
}
