<?php

namespace Tests\Feature\Api;

use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_log_in_and_receive_a_token(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create(['password' => bcrypt('secret123'), 'disabled_at' => now()]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();
    }

    public function test_token_is_revoked_on_logout_and_cannot_be_reused(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $token = $user->createToken('phpunit', ['staff'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Laravel's RequestGuard caches the resolved user on the guard
        // instance, which (only within a single test process reusing the
        // container across simulated requests — never across real, separate
        // HTTP requests) would mask a revoked token unless cleared here.
        Auth::forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_a_child_token_cannot_reach_staff_endpoints(): void
    {
        $org = Organization::factory()->create();
        $child = ChildProfile::factory()->for($org)->create();
        $token = $child->createToken('phpunit', ['child'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_a_staff_token_cannot_reach_child_endpoints(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $token = $user->createToken('phpunit', ['staff'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/child/me')
            ->assertForbidden();
    }

    public function test_revoked_device_cannot_activate_via_api(): void
    {
        $org = Organization::factory()->create();
        $creator = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $device = new DeviceAssociation(['device_identifier' => 'APIREV001', 'status' => 'revoked', 'expires_at' => now()->addDays(7)]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $creator->id;
        $device->setPin('9911');
        $device->save();

        $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'APIREV001',
            'pin' => '9911',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();
    }

    public function test_expired_device_cannot_activate_via_api(): void
    {
        $org = Organization::factory()->create();
        $creator = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $device = new DeviceAssociation(['device_identifier' => 'APIEXP001', 'status' => 'active', 'expires_at' => now()->subDay()]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $creator->id;
        $device->setPin('9912');
        $device->save();

        $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'APIEXP001',
            'pin' => '9912',
            'device_name' => 'phpunit',
        ])->assertUnprocessable();
    }
}
