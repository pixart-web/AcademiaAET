<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\ConsentRecord;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_professional_can_register_and_revoke_consent(): void
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

        $this->actingAs($pro)
            ->post(route('children.consents.store', $child), ['type' => 'tratamento_dados', 'text_version' => '2026-01'])
            ->assertRedirect();

        $consent = ConsentRecord::where('child_profile_id', $child->id)->firstOrFail();
        $this->assertSame($pro->id, $consent->granted_by_user_id);
        $this->assertTrue($consent->isActive());

        $this->actingAs($pro)
            ->patch(route('children.consents.revoke', [$child, $consent]))
            ->assertRedirect();

        $this->assertFalse($consent->fresh()->isActive());
    }

    public function test_unassigned_professional_cannot_register_consent(): void
    {
        $org = Organization::factory()->create();
        $outsider = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $this->actingAs($outsider)
            ->post(route('children.consents.store', $child), ['type' => 'tratamento_dados', 'text_version' => '2026-01'])
            ->assertForbidden();
    }
}
