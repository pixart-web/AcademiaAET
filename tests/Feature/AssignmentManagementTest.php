<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_assign_a_published_activity_from_the_child_page(): void
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

        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id, 'status' => 'published']);
        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);
        $versioning->publish($version);

        $this->actingAs($pro)
            ->post(route('children.assignments.store', $child), ['activity_id' => $activity->id])
            ->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'child_profile_id' => $child->id,
            'activity_version_id' => $activity->fresh()->current_version_id,
            'status' => 'assigned',
        ]);
    }

    public function test_professional_can_cancel_an_assignment(): void
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
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
            'status' => 'assigned',
        ]);

        $this->actingAs($pro)->delete(route('assignments.cancel', $assignment))->assertRedirect();

        $this->assertSame('cancelled', $assignment->fresh()->status->value);
    }

    public function test_revoked_device_association_can_no_longer_activate(): void
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

        $device = new DeviceAssociation(['device_identifier' => 'REVOKEME1', 'status' => 'active', 'expires_at' => now()->addDays(7)]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $pro->id;
        $device->setPin('4242');
        $device->save();

        $this->actingAs($pro)
            ->patch(route('children.devices.revoke', [$child, $device]))
            ->assertRedirect();

        $this->assertSame('revoked', $device->fresh()->status);

        $this->post('/crianca/entrar/ativar', ['device_code' => 'REVOKEME1', 'pin' => '4242'])
            ->assertSessionHasErrors('pin');
        $this->assertGuest('child');
    }
}
