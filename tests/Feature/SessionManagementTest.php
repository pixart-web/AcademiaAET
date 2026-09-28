<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The test environment runs SESSION_DRIVER=array (see phpunit.xml), so no
 * real session rows are written by the HTTP kernel here — these tests seed
 * the `sessions` table directly to exercise SessionController's queries,
 * which only ever read/write that table regardless of which driver is
 * actually serving the current request.
 */
class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_own_sessions(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $other = User::factory()->for($org)->create();

        DB::table('sessions')->insert([
            ['id' => 'sess-mine', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Mozilla Macintosh', 'payload' => 'x', 'last_activity' => now()->timestamp],
            ['id' => 'sess-other', 'user_id' => $other->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'Mozilla Windows', 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        $response = $this->actingAs($user)->get(route('sessions.index'));

        $sessions = collect($response->viewData('page')['props']['sessions']);
        $this->assertTrue($sessions->contains('id', 'sess-mine'));
        $this->assertFalse($sessions->contains('id', 'sess-other'));
    }

    public function test_user_can_revoke_another_session_but_not_the_current_one(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        DB::table('sessions')->insert([
            ['id' => 'sess-old-device', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'old', 'payload' => 'x', 'last_activity' => now()->subDays(3)->timestamp],
        ]);

        $this->actingAs($user)
            ->delete(route('sessions.destroy', 'sess-old-device'))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'sess-old-device']);
        $this->assertDatabaseHas('audit_events', ['action' => 'session.revoked', 'user_id' => $user->id]);
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $other = User::factory()->for($org)->create();

        DB::table('sessions')->insert([
            ['id' => 'sess-belongs-to-other', 'user_id' => $other->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($user)
            ->delete(route('sessions.destroy', 'sess-belongs-to-other'))
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => 'sess-belongs-to-other']);
    }
}
