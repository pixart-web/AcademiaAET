<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class AuditEventViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_events_for_their_organization(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        AuditEvent::create(['organization_id' => $org->id, 'user_id' => $admin->id, 'action' => 'child.profile_updated']);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('events.total', 1)
                ->etc()
            );
    }

    public function test_professional_cannot_view_audit_events(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $this->actingAs($pro)->get(route('audit.index'))->assertForbidden();
    }

    public function test_admin_never_sees_another_organizations_events(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        AuditEvent::create(['organization_id' => $otherOrg->id, 'action' => 'staff.login']);

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('events.total', 0)
                ->etc()
            );
    }

    public function test_events_can_be_filtered_by_action(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        AuditEvent::create(['organization_id' => $org->id, 'action' => 'child.profile_updated']);
        AuditEvent::create(['organization_id' => $org->id, 'action' => 'staff.login']);

        $this->actingAs($admin)
            ->get(route('audit.index', ['action' => 'child']))
            ->assertInertia(fn (AssertableJson $page) => $page
                ->where('events.total', 1)
                ->etc()
            );
    }
}
