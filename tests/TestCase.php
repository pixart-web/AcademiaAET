<?php

namespace Tests;

use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Logs a test in as a child the way the real app does it — through an
     * activated DeviceAssociation with its id in the session — rather than
     * the bare `actingAs($child, 'child')`, which EnsureChildDeviceIsActive
     * (AET-RC01 finding 2) now rejects on the very next request: a child
     * session middleware can't tell from a bare guard login which device,
     * if any, it's supposed to belong to, so it treats that as a session
     * with no verifiable device and logs it back out.
     */
    protected function actingAsChild(ChildProfile $child): static
    {
        $device = new DeviceAssociation([
            'device_identifier' => Str::upper(Str::random(10)),
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = User::factory()->for($child->organization)->create()->id;
        $device->setPin('0000');
        $device->activateWithToken(Str::random(48));
        $device->session_expires_at = now()->addYear();
        $device->save();

        $this->actingAs($child, 'child');
        $this->withSession(['child_device_association_id' => $device->id]);

        // actingAs($user, 'child') calls Auth::shouldUse('child') under the
        // hood, which — surprisingly — doesn't just set a "last used"
        // hint: AuthManager::shouldUse() calls setDefaultDriver(), which
        // mutates the actual auth.defaults.guard config value for the rest
        // of this app container's lifetime (one whole test method, since
        // RefreshDatabase reuses it across simulated requests). Left as
        // 'child', a later bare actingAs($staffUser) — no guard argument —
        // would resolve guard(null) to 'child' instead of 'web' and log
        // the staff user into the wrong guard entirely. Restoring it here
        // means actingAsChild() never leaks this past its own call.
        $this->app['auth']->shouldUse('web');

        return $this;
    }
}
