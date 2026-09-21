<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAssociationTest extends TestCase
{
    use RefreshDatabase;

    private function makeDevice(string $pin = '1234'): DeviceAssociation
    {
        $org = Organization::factory()->create();
        $creator = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $device = new DeviceAssociation([
            'device_identifier' => 'TESTCODE1',
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $creator->id;
        $device->setPin($pin);
        $device->save();

        return $device;
    }

    public function test_wrong_pin_does_not_activate_the_device(): void
    {
        $this->makeDevice('1234');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'TESTCODE1', 'pin' => '0000'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest('child');
    }

    public function test_correct_code_and_pin_activate_and_log_in(): void
    {
        $device = $this->makeDevice('1234');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'testcode1', 'pin' => '1234'])
            ->assertRedirect(route('child.home'));

        $this->assertAuthenticated('child');
        $this->assertTrue($device->fresh()->isActivated());
    }

    public function test_revoked_device_cannot_activate(): void
    {
        $device = $this->makeDevice('1234');
        $device->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->post('/crianca/entrar/ativar', ['device_code' => 'TESTCODE1', 'pin' => '1234'])
            ->assertSessionHasErrors('pin');

        $this->assertGuest('child');
    }
}
