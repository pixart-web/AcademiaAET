<?php

namespace Tests\Feature;

use App\Enums\ChildStatus;
use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * AET-RC01 finding 2: revoking or expiring a DeviceAssociation had no
 * effect on a child session or API token already issued from it — both
 * kept working until the browser/app session naturally ended. This
 * exercises the real HTTP flow end to end (not just the service layer),
 * since the whole point of the fix is that *every* authenticated surface
 * (web session, API token, the media-streaming route with no guard
 * middleware of its own) re-checks the device on every request.
 */
class DeviceRevocationPropagationTest extends TestCase
{
    use RefreshDatabase;

    private function makeChildWithDevice(string $code, string $pin): array
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $device = new DeviceAssociation(['device_identifier' => $code, 'status' => 'active', 'expires_at' => now()->addDays(7)]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $pro->id;
        $device->setPin($pin);
        $device->save();

        return [$org, $pro, $child, $device];
    }

    public function test_revoking_a_device_ends_its_web_session_on_the_very_next_request(): void
    {
        [, , $child, $device] = $this->makeChildWithDevice('REVOKEWEB', '1111');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'REVOKEWEB', 'pin' => '1111'])
            ->assertRedirect(route('child.home'));
        $this->get('/crianca')->assertOk();

        $device->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->get('/crianca')->assertRedirect(route('child.login'));
        $this->assertGuest('child');
    }

    public function test_revoking_one_device_does_not_affect_a_different_device_of_the_same_child(): void
    {
        [$org, $pro, $child] = $this->makeChildWithDevice('DEVICEONE', '1111');
        $deviceTwo = new DeviceAssociation(['device_identifier' => 'DEVICETWO', 'status' => 'active', 'expires_at' => now()->addDays(7)]);
        $deviceTwo->child_profile_id = $child->id;
        $deviceTwo->created_by_user_id = $pro->id;
        $deviceTwo->setPin('2222');
        $deviceTwo->save();

        $this->post('/crianca/entrar/ativar', ['device_code' => 'DEVICEONE', 'pin' => '1111']);
        $deviceOne = DeviceAssociation::where('device_identifier', 'DEVICEONE')->firstOrFail();
        $deviceOne->update(['status' => 'revoked', 'revoked_at' => now()]);

        // A fresh session (simulating the second device/tab) activating
        // independently must be completely unaffected by the first's
        // revocation.
        $this->post('/crianca/sair');
        $this->post('/crianca/entrar/ativar', ['device_code' => 'DEVICETWO', 'pin' => '2222'])
            ->assertRedirect(route('child.home'));
        $this->get('/crianca')->assertOk();
    }

    public function test_disabling_a_child_profile_ends_its_session_on_the_next_request(): void
    {
        [, , $child] = $this->makeChildWithDevice('DISABLEME', '1111');

        $this->post('/crianca/entrar/ativar', ['device_code' => 'DISABLEME', 'pin' => '1111']);
        $this->get('/crianca')->assertOk();

        $child->update(['status' => ChildStatus::Archived]);
        // The 'child' guard caches its resolved user for the rest of this
        // PHP process (see the project's established note on this — it
        // never happens across genuinely separate real HTTP requests in
        // production, only within one PHPUnit test method reusing the same
        // container). Forgetting it forces the next request to resolve
        // the child fresh from the database, exactly like a real second
        // request would.
        $this->app['auth']->forgetGuards();

        $this->get('/crianca')->assertRedirect(route('child.login'));
    }

    public function test_a_child_session_established_without_a_recorded_device_is_treated_as_invalid(): void
    {
        [, , $child] = $this->makeChildWithDevice('NEVERUSED', '1111');

        // The bare actingAs(), with no child_device_association_id in the
        // session — exactly what a pre-fix session looked like. Must be
        // rejected, not grandfathered in.
        $this->actingAs($child, 'child');

        $this->get('/crianca')->assertRedirect(route('child.login'));
    }

    /**
     * media.show has no guard middleware of its own (MediaStreamController
     * resolves web/child/sanctum principals manually — see its docblock),
     * so for a web session EnsureChildDeviceIsActive already catches a
     * revoked device before the request even reaches it. The API/Sanctum
     * path is the one case nothing else protects, which is why this test
     * goes through a real API-issued token rather than a web session: it's
     * the only way to actually exercise MediaStreamController's own
     * device check rather than just the global web middleware's.
     */
    public function test_revoked_devices_media_access_is_cut_off_immediately_via_the_api(): void
    {
        Storage::fake('local');

        [$org, $pro, $child, $device] = $this->makeChildWithDevice('MEDIAREV', '1111');
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $media = MediaAsset::factory()->for($org)->create(['kind' => 'image']);
        Storage::disk('local')->put($media->path, 'fake-image-bytes');

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::CompletionConfirmation->value, 'instruction_media_asset_id' => $media->id],
        ]);
        $versioning->publish($version);

        Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        $token = $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'MEDIAREV', 'pin' => '1111', 'device_name' => 'phpunit',
        ])->json('token');

        $signedUrl = URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $media->id]);
        $this->withHeader('Authorization', "Bearer {$token}")->get($signedUrl)->assertOk();

        $device->update(['status' => 'revoked', 'revoked_at' => now()]);

        // The signed URL itself is still cryptographically valid — only
        // the device-state re-check (MediaStreamController::childDeviceIsActive())
        // stops this.
        $this->withHeader('Authorization', "Bearer {$token}")->get($signedUrl)->assertForbidden();
    }
}
