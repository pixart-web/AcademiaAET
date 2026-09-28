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

class OverdueAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_show_page_flags_an_overdue_assignment(): void
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

        $overdue = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
            'status' => 'assigned',
            'due_at' => now()->subDay(),
        ]);

        $notYetDue = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
            'status' => 'assigned',
            'due_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($pro)->get(route('children.show', $child));
        $response->assertOk();

        $assignments = collect($response->viewData('page')['props']['child']['assignments']);

        $this->assertTrue($assignments->firstWhere('id', $overdue->id)['is_overdue']);
        $this->assertFalse($assignments->firstWhere('id', $notYetDue->id)['is_overdue']);
    }
}
