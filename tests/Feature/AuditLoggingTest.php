<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\AuditEvent;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_is_audited(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create(['password' => bcrypt('secret123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'staff.login',
            'user_id' => $user->id,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_account_disable_and_reactivate_are_audited(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $target = User::factory()->for($org)->create();

        $this->actingAs($admin)->delete(route('staff.destroy', $target));
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.disabled', 'auditable_id' => $target->id]);

        $this->actingAs($admin)->patch(route('staff.reactivate', $target));
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.reactivated', 'auditable_id' => $target->id]);
    }

    public function test_device_issuance_and_revocation_are_audited(): void
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

        $this->actingAs($pro)->post(route('children.devices.store', $child));

        $event = AuditEvent::where('action', 'device.activation_issued')->first();
        $this->assertNotNull($event);
        $this->assertSame($child->id, $event->auditable_id);

        $deviceId = $event->metadata['device_association_id'];
        $this->actingAs($pro)->patch(route('children.devices.revoke', [$child, $deviceId]));

        $this->assertDatabaseHas('audit_events', ['action' => 'device.revoked', 'auditable_id' => $child->id]);
    }

    public function test_activity_publish_and_archive_are_audited(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);

        $this->actingAs($pro)->post(route('activities.publish', $activity));
        $this->assertDatabaseHas('audit_events', ['action' => 'activity.published', 'auditable_id' => $activity->id]);

        $this->actingAs($pro)->post(route('activities.archive', $activity));
        $this->assertDatabaseHas('audit_events', ['action' => 'activity.archived', 'auditable_id' => $activity->id]);
    }

    public function test_professional_assignment_and_guardian_association_are_audited(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $this->actingAs($admin)->post(route('children.professionals.store', $child), ['user_id' => $pro->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'professional.assigned', 'auditable_id' => $child->id]);

        $this->actingAs($admin)->post(route('children.guardians.store', $child), [
            'name' => 'Encarregada Teste', 'email' => 'encarregada@example.test', 'relationship_type' => 'mãe',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'guardian.associated', 'auditable_id' => $child->id]);
    }
}
