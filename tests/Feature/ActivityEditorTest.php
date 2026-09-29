<?php

namespace Tests\Feature;

use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\ChildProfile;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_can_create_an_activity_with_steps_via_http(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $response = $this->actingAs($pro)->post(route('activities.store'), [
            'title' => 'Nova atividade HTTP',
            'description' => 'Descrição de teste',
            'category' => 'emocional',
            'steps' => [
                [
                    'title' => 'Passo 1',
                    'response_type' => ResponseType::SingleChoice->value,
                    'response_config' => ['options' => ['Sim', 'Não'], 'correct' => 'Sim'],
                ],
                [
                    'title' => 'Passo 2',
                    'response_type' => ResponseType::ShortText->value,
                ],
            ],
        ]);

        $activity = Activity::where('title', 'Nova atividade HTTP')->firstOrFail();
        $response->assertRedirect(route('activities.edit', $activity));

        $this->assertSame('draft', $activity->status->value);
        $this->assertSame(2, $activity->currentVersion->steps()->count());
    }

    public function test_creating_an_activity_without_steps_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $this->actingAs($pro)
            ->post(route('activities.store'), ['title' => 'Sem passos', 'steps' => []])
            ->assertSessionHasErrors('steps');

        $this->assertDatabaseMissing('activities', ['title' => 'Sem passos']);
    }

    public function test_editing_a_draft_activity_updates_it_in_place_via_http(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $versioning->createInitialVersion($activity, $pro, 'Título original', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);
        $originalVersionId = $activity->fresh()->current_version_id;

        $this->actingAs($pro)->put(route('activities.update', $activity), [
            'title' => 'Título editado',
            'steps' => [['response_type' => ResponseType::ShortText->value]],
        ])->assertRedirect(route('activities.edit', $activity));

        $activity->refresh();
        $this->assertSame('Título editado', $activity->title);
        $this->assertSame($originalVersionId, $activity->current_version_id);
    }

    public function test_editing_an_assigned_activity_via_http_forks_a_new_version(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'V1', null, null, [
            ['response_type' => ResponseType::ShortText->value],
        ]);
        $versioning->publish($version);

        Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);

        $this->actingAs($pro)->put(route('activities.update', $activity), [
            'title' => 'V2',
            'steps' => [['response_type' => ResponseType::ShortText->value]],
        ]);

        $activity->refresh();
        $this->assertNotSame($version->id, $activity->current_version_id);
        $this->assertSame('V1', $version->fresh()->title);
    }

    public function test_unassigned_professional_cannot_edit_another_professionals_activity(): void
    {
        $org = Organization::factory()->create();
        $author = User::factory()->for($org)->create();
        $outsider = User::factory()->for($org)->create();
        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $author->id]);

        $this->actingAs($outsider)
            ->put(route('activities.update', $activity), ['title' => 'x', 'steps' => [['response_type' => 'short_text']]])
            ->assertForbidden();
    }
}
