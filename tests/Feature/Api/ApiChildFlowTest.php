<?php

namespace Tests\Feature\Api;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\DeviceAssociation;
use App\Models\Organization;
use App\Models\RewardEvent;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiChildFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeAssignment(): Assignment
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);

        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade API', null, null, [
            ['response_type' => ResponseType::CompletionConfirmation->value],
        ]);
        $versioning->publish($version);

        return Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);
    }

    private function deviceFor(ChildProfile $child, User $creator, string $code, string $pin): DeviceAssociation
    {
        $device = new DeviceAssociation(['device_identifier' => $code, 'status' => 'active', 'expires_at' => now()->addDays(7)]);
        $device->child_profile_id = $child->id;
        $device->created_by_user_id = $creator->id;
        $device->setPin($pin);
        $device->save();

        return $device;
    }

    public function test_full_flow_activate_start_answer_submit(): void
    {
        $assignment = $this->makeAssignment();
        $child = $assignment->childProfile;
        $pro = $assignment->assignedBy;

        $this->deviceFor($child, $pro, 'APIFLOW01', '1122');

        $activation = $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'APIFLOW01',
            'pin' => '1122',
            'device_name' => 'phpunit',
        ])->assertOk()->json();

        $token = $activation['token'];
        $auth = ['Authorization' => "Bearer {$token}"];

        $this->withHeaders($auth)->getJson('/api/v1/child/me')
            ->assertOk()
            ->assertJsonPath('id', $child->id);

        $start = $this->withHeaders($auth)
            ->postJson("/api/v1/child/assignments/{$assignment->id}/start")
            ->assertOk()
            ->json();

        $attemptId = $start['attempt']['id'];
        $stepId = $start['steps'][0]['id'];

        $this->withHeaders($auth)
            ->postJson("/api/v1/child/attempts/{$attemptId}/steps/{$stepId}", ['value' => true])
            ->assertOk();

        $this->withHeaders($auth)
            ->postJson("/api/v1/child/attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('attempt.status', 'submitted');

        $this->assertSame(1, RewardEvent::where('attempt_id', $attemptId)->count());
    }

    public function test_resubmitting_via_api_does_not_duplicate_rewards(): void
    {
        $assignment = $this->makeAssignment();
        $child = $assignment->childProfile;
        $pro = $assignment->assignedBy;
        $this->deviceFor($child, $pro, 'APIFLOW02', '3344');

        $token = $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'APIFLOW02', 'pin' => '3344', 'device_name' => 'phpunit',
        ])->json('token');
        $auth = ['Authorization' => "Bearer {$token}"];

        $start = $this->withHeaders($auth)
            ->postJson("/api/v1/child/assignments/{$assignment->id}/start")
            ->json();
        $attemptId = $start['attempt']['id'];
        $stepId = $start['steps'][0]['id'];

        $this->withHeaders($auth)
            ->postJson("/api/v1/child/attempts/{$attemptId}/steps/{$stepId}", ['value' => true])
            ->assertOk();

        $this->withHeaders($auth)->postJson("/api/v1/child/attempts/{$attemptId}/submit")->assertOk();
        $this->withHeaders($auth)->postJson("/api/v1/child/attempts/{$attemptId}/submit")->assertOk();

        $this->assertSame(1, RewardEvent::where('attempt_id', $attemptId)->count());
    }

    public function test_a_child_cannot_access_another_childs_assignment_via_api(): void
    {
        $assignment = $this->makeAssignment();
        $otherChild = ChildProfile::factory()->for($assignment->childProfile->organization)->create();
        $token = $otherChild->createToken('phpunit', ['child'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/child/assignments/{$assignment->id}/start")
            ->assertForbidden();
    }

    public function test_device_unlock_reissues_a_token_without_the_activation_code(): void
    {
        $assignment = $this->makeAssignment();
        $child = $assignment->childProfile;
        $pro = $assignment->assignedBy;
        $this->deviceFor($child, $pro, 'APIFLOW03', '5566');

        $activation = $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'APIFLOW03', 'pin' => '5566', 'device_name' => 'phpunit',
        ])->json();

        $this->postJson('/api/v1/child/device/unlock', [
            'device_id' => $activation['device_id'],
            'device_token' => $activation['device_token'],
            'pin' => '5566',
            'device_name' => 'phpunit-2',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_device_unlock_fails_with_wrong_device_token(): void
    {
        $assignment = $this->makeAssignment();
        $child = $assignment->childProfile;
        $pro = $assignment->assignedBy;
        $device = $this->deviceFor($child, $pro, 'APIFLOW04', '7788');

        $activation = $this->postJson('/api/v1/child/device/activate', [
            'device_code' => 'APIFLOW04', 'pin' => '7788', 'device_name' => 'phpunit',
        ])->json();

        $this->postJson('/api/v1/child/device/unlock', [
            'device_id' => $activation['device_id'],
            'device_token' => 'not-the-real-token',
            'pin' => '7788',
            'device_name' => 'phpunit-2',
        ])->assertUnprocessable();
    }
}
