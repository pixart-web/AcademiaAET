<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaFullFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_mfa_setup_and_login_challenge_flow(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create(['password' => bcrypt('secret123')]);
        $google2fa = app(Google2FA::class);

        // Step 1: request setup — no code yet, just generates + stashes a secret.
        $this->actingAs($user)->post(route('mfa.enable'))->assertRedirect();

        $secret = session('mfa.setup_secret');
        $this->assertNotNull($secret);

        // Step 2: wrong code is rejected, MFA stays off.
        $this->actingAs($user)->post(route('mfa.enable'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->mfa_enabled);

        // Step 3: correct TOTP code turns MFA on.
        $validCode = $google2fa->getCurrentOtp($secret);
        $this->actingAs($user)->post(route('mfa.enable'), ['code' => $validCode])->assertRedirect(route('mfa.edit'));
        $this->assertTrue($user->fresh()->mfa_enabled);

        // Step 4: logging in now requires the MFA challenge, not a direct
        // session. actingAs() above leaves the 'web' guard resolved for the
        // rest of the test process (not just the request it was for), so a
        // plain POST here would otherwise be caught by the 'guest' middleware
        // and never reach the login logic at all — log out first, like a
        // real subsequent visitor would be.
        Auth::guard('web')->logout();
        $this->app['auth']->forgetGuards();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();

        $this->post('/mfa-challenge', ['code' => $google2fa->getCurrentOtp($secret)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_mfa_can_be_disabled(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create(['mfa_enabled' => true, 'mfa_secret' => encrypt('SECRET')]);

        $this->actingAs($user)->delete(route('mfa.disable'))->assertRedirect(route('mfa.edit'));

        $this->assertFalse($user->fresh()->mfa_enabled);
        $this->assertNull($user->fresh()->mfa_secret);
    }
}
