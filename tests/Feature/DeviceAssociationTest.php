<?php

namespace Tests\Feature;

use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\Organization;
use App\Models\User;
use App\Services\DeviceAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    /**
     * AET-RC01 finding 3: DeviceAuthService::activate() accepted the same
     * activation code + PIN more than once, silently re-issuing a new
     * device token each time (which also invalidated whatever token the
     * legitimate device already held) — anyone who had ever seen that
     * one-time code (e.g. written down at pairing time) could reactivate
     * and hijack the session at any later point, not just once.
     */
    public function test_reactivating_an_already_activated_device_is_rejected(): void
    {
        $device = $this->makeDevice('1234');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'TESTCODE1', 'pin' => '1234'])
            ->assertRedirect(route('child.home'));
        $firstTokenHash = $device->fresh()->device_token_hash;

        $this->post('/crianca/sair');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'TESTCODE1', 'pin' => '1234'])
            ->assertSessionHasErrors('pin');

        // Not just rejected — the original device's token must still be
        // the one stored, never silently replaced by the second attempt.
        $this->assertSame($firstTokenHash, $device->fresh()->device_token_hash);
    }

    public function test_reactivation_failure_does_not_reveal_that_the_code_was_already_used(): void
    {
        $this->makeDevice('1234');
        $this->post('/crianca/entrar/ativar', ['device_code' => 'TESTCODE1', 'pin' => '1234']);
        $this->post('/crianca/sair');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'TESTCODE1', 'pin' => '1234']);
        $alreadyUsed = session('errors')['default']['messages']['pin'][0];

        $this->post('/crianca/entrar/ativar', ['device_code' => 'NOSUCHCODE', 'pin' => '1234']);
        $neverExisted = session('errors')['default']['messages']['pin'][0];

        $this->assertSame($neverExisted, $alreadyUsed);
    }

    public function test_concurrent_activation_attempts_only_let_one_succeed(): void
    {
        $device = $this->makeDevice('1234');

        // Simulates two near-simultaneous requests racing for the same
        // one-time code: DeviceAuthService::activate() locks the row
        // (lockForUpdate) inside a transaction, so the second call — even
        // issued before the first's HTTP response comes back in a real
        // race — only ever runs after the first's transaction commits,
        // and by then sees activated_at already set.
        $attempts = app(DeviceAuthService::class);
        [, $tokenA] = $attempts->activate('TESTCODE1', '1234');

        try {
            $attempts->activate('TESTCODE1', '1234');
            $this->fail('Expected the second activation to be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pin', $e->errors());
        }

        $this->assertNotNull($tokenA);
        $this->assertTrue($device->fresh()->checkDeviceToken($tokenA));
    }

    public function test_an_already_activated_device_stays_usable_past_the_activation_codes_own_short_deadline(): void
    {
        $this->makeDevice('1234');
        [$activated, $token] = app(DeviceAuthService::class)->activate('TESTCODE1', '1234');

        // The activation code's own `expires_at` is already in the past —
        // proving unlock() no longer depends on it (a separate
        // session_expires_at governs the ongoing device, per
        // DeviceAssociation::isSessionUsable()).
        $activated->forceFill(['expires_at' => now()->subDay()])->save();

        $unlocked = app(DeviceAuthService::class)->unlock($activated->fresh(), $token, '1234');

        $this->assertNotNull($unlocked);
    }
}
