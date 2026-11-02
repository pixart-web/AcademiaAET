<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityVersion;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\ClinicalNote;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_without_assignment_cannot_view_child_profile(): void
    {
        $org = Organization::factory()->create();
        $assignedPro = User::factory()->for($org)->create();
        $otherPro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        ProfessionalAssignment::create([
            'child_profile_id' => $child->id,
            'user_id' => $assignedPro->id,
            'assigned_by_user_id' => $assignedPro->id,
            'active' => true,
            'started_at' => now(),
        ]);

        $this->actingAs($otherPro)->get(route('children.show', $child))->assertForbidden();
        $this->actingAs($assignedPro)->get(route('children.show', $child))->assertOk();
    }

    public function test_professional_without_assignment_cannot_view_attempt_evaluation(): void
    {
        $org = Organization::factory()->create();
        $assignedPro = User::factory()->for($org)->create();
        $otherPro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        ProfessionalAssignment::create([
            'child_profile_id' => $child->id,
            'user_id' => $assignedPro->id,
            'assigned_by_user_id' => $assignedPro->id,
            'active' => true,
            'started_at' => now(),
        ]);

        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $assignedPro->id]);
        $version = ActivityVersion::factory()->for($activity)->create(['created_by_user_id' => $assignedPro->id]);
        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $assignedPro->id,
        ]);
        $attempt = Attempt::factory()->for($assignment)->create();

        $this->actingAs($otherPro)->get(route('evaluations.show', $attempt))->assertForbidden();
    }

    public function test_clinical_note_never_appears_in_child_facing_response(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $version = ActivityVersion::factory()->for($activity)->create(['created_by_user_id' => $pro->id]);
        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);
        $attempt = Attempt::factory()->for($assignment)->create();

        ClinicalNote::create([
            'child_profile_id' => $child->id,
            'author_user_id' => $pro->id,
            'attempt_id' => $attempt->id,
            'body' => 'Informação clínica sensível que nunca deve sair do portal profissional.',
        ]);

        $response = $this->actingAsChild($child)->get('/crianca')->assertOk();
        $response->assertDontSee('Informação clínica sensível', false);
    }
}
