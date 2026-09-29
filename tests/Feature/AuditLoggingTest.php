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
        // The audit trail existing is not the same as the account actually
        // being disabled — assert the real state, not just the side effect.
        $this->assertNotNull($target->fresh()->disabled_at);

        $this->actingAs($admin)->patch(route('staff.reactivate', $target));
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.reactivated', 'auditable_id' => $target->id]);
        $this->assertNull($target->fresh()->disabled_at);
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

    /**
     * Regression: child profile edits and activity content edits were the
     * two gaps explicitly called out in docs/requirements-matrix.md as
     * "ainda não coberto" by the audit trail (only publish/archive/delete
     * were). Neither log the actual clinical text or activity content —
     * only that an edit happened, by whom, and (for activities) whether it
     * forked a new version.
     */
    public function test_child_profile_edit_is_audited_without_leaking_care_notes(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create(['care_notes' => 'Nota antiga']);

        $this->actingAs($admin)->put(route('children.update', $child), [
            'first_name' => $child->first_name,
            'birth_date' => $child->birth_date->format('Y-m-d'),
            'status' => $child->status->value,
            'visual_experience' => $child->visual_experience->value,
            'care_notes' => 'Nota nova',
        ]);

        $event = AuditEvent::where('action', 'child.profile_updated')->first();
        $this->assertNotNull($event);
        $this->assertSame($child->id, $event->auditable_id);
        $this->assertTrue($event->metadata['care_notes_changed']);
        $this->assertStringNotContainsString('Nota nova', json_encode($event->metadata));
        $this->assertStringNotContainsString('Nota antiga', json_encode($event->metadata));
    }

    public function test_activity_content_edit_is_audited_and_flags_version_fork(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);

        // Editing an unassigned draft updates in place — no fork.
        $this->actingAs($pro)->put(route('activities.update', $activity), [
            'title' => 'Atividade editada',
            'category' => null,
            'area' => null,
            'difficulty' => null,
            'instructions' => null,
            'evaluation_criteria' => null,
            'steps' => [['response_type' => ResponseType::ShortText->value]],
        ]);

        $event = AuditEvent::where('action', 'activity.content_updated')->first();
        $this->assertNotNull($event);
        $this->assertSame($activity->id, $event->auditable_id);
        $this->assertFalse($event->metadata['forked_new_version']);

        // Publish, then edit again — this time it must fork.
        $this->actingAs($pro)->post(route('activities.publish', $activity));

        $this->actingAs($pro)->put(route('activities.update', $activity), [
            'title' => 'Atividade editada outra vez',
            'category' => null,
            'area' => null,
            'difficulty' => null,
            'instructions' => null,
            'evaluation_criteria' => null,
            'steps' => [['response_type' => ResponseType::ShortText->value]],
        ]);

        $forkEvent = AuditEvent::where('action', 'activity.content_updated')->latest('id')->first();
        $this->assertTrue($forkEvent->metadata['forked_new_version']);
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
