<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_page_shows_correct_assignment_counts(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        ProfessionalAssignment::create([
            'child_profile_id' => $child->id,
            'user_id' => $pro->id,
            'assigned_by_user_id' => $pro->id,
            'active' => true,
            'started_at' => now(),
        ]);

        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);

        Assignment::factory()->create([
            'child_profile_id' => $child->id, 'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id, 'status' => 'assigned',
        ]);
        Assignment::factory()->create([
            'child_profile_id' => $child->id, 'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id, 'status' => 'submitted',
        ]);
        Assignment::factory()->create([
            'child_profile_id' => $child->id, 'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id, 'status' => 'reviewed',
        ]);
        Assignment::factory()->create([
            'child_profile_id' => $child->id, 'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id, 'status' => 'assigned', 'due_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($pro)->get(route('children.show', $child));
        $summary = $response->viewData('page')['props']['summary'];

        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(1, $summary['pending_evaluation']);
        $this->assertSame(1, $summary['overdue']);
    }
}
