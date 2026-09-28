<?php

namespace Tests\Feature;

use App\Enums\MediaKind;
use App\Enums\ResponseType;
use App\Models\Activity;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\ChildProfile;
use App\Models\ClinicalNote;
use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\ProfessionalAssignment;
use App\Models\StepResponse;
use App\Models\User;
use App\Services\ActivityVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChildDataExportAndErasureTest extends TestCase
{
    use RefreshDatabase;

    private function makeChildWithRecording(): array
    {
        Storage::fake('local');

        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->for($org)->create();
        $pro = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        ProfessionalAssignment::create([
            'child_profile_id' => $child->id,
            'user_id' => $pro->id,
            'assigned_by_user_id' => $admin->id,
            'active' => true,
            'started_at' => now(),
        ]);

        $activity = Activity::factory()->for($org)->create(['created_by_user_id' => $pro->id]);
        $versioning = app(ActivityVersioningService::class);
        $version = $versioning->createInitialVersion($activity, $pro, 'Atividade', null, null, [
            ['response_type' => ResponseType::VoiceRecording->value],
        ]);
        $versioning->publish($version);

        $assignment = Assignment::factory()->create([
            'child_profile_id' => $child->id,
            'activity_version_id' => $version->id,
            'assigned_by_user_id' => $pro->id,
        ]);
        $attempt = Attempt::factory()->for($assignment)->create();

        $file = UploadedFile::fake()->create('nota.webm', 5, 'audio/webm');
        $path = $file->store('recordings/test', 'local');
        $media = MediaAsset::create([
            'organization_id' => $org->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'audio/webm',
            'kind' => MediaKind::Audio,
            'size_bytes' => 5000,
            'status' => 'active',
        ]);

        StepResponse::create([
            'attempt_id' => $attempt->id,
            'activity_step_id' => $version->steps()->first()->id,
            'media_asset_id' => $media->id,
            'answered_at' => now(),
        ]);

        ClinicalNote::create([
            'child_profile_id' => $child->id,
            'author_user_id' => $pro->id,
            'body' => 'Nota clínica confidencial de teste.',
        ]);

        return compact('org', 'admin', 'pro', 'child', 'media');
    }

    public function test_assigned_professional_can_export_full_data_including_clinical_notes(): void
    {
        ['pro' => $pro, 'child' => $child] = $this->makeChildWithRecording();

        $response = $this->actingAs($pro)->get(route('children.export', $child));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $response->assertJsonPath('clinical_notes.0.body', 'Nota clínica confidencial de teste.');
    }

    public function test_unassigned_professional_cannot_export(): void
    {
        $org = Organization::factory()->create();
        $outsider = User::factory()->for($org)->create();
        $child = ChildProfile::factory()->for($org)->create();

        $this->actingAs($outsider)->get(route('children.export', $child))->assertForbidden();
    }

    public function test_professional_cannot_erase_only_admin_can(): void
    {
        ['pro' => $pro, 'child' => $child] = $this->makeChildWithRecording();

        $this->actingAs($pro)
            ->delete(route('children.erase', $child), ['confirm_name' => $child->first_name])
            ->assertForbidden();

        $this->assertNotNull(ChildProfile::withTrashed()->find($child->id));
    }

    public function test_erasure_requires_the_exact_confirmation_name(): void
    {
        ['admin' => $admin, 'child' => $child] = $this->makeChildWithRecording();

        $this->actingAs($admin)
            ->delete(route('children.erase', $child), ['confirm_name' => 'nome errado'])
            ->assertSessionHasErrors('confirm_name');

        $this->assertNotNull(ChildProfile::withTrashed()->find($child->id));
    }

    public function test_erasure_permanently_removes_the_child_and_cascades_and_deletes_the_recording_file(): void
    {
        ['admin' => $admin, 'child' => $child, 'media' => $media] = $this->makeChildWithRecording();

        Storage::disk('local')->assertExists($media->path);

        $this->actingAs($admin)
            ->delete(route('children.erase', $child), ['confirm_name' => $child->first_name])
            ->assertRedirect(route('children.index'));

        $this->assertNull(ChildProfile::withTrashed()->find($child->id));
        $this->assertSame(0, Assignment::where('child_profile_id', $child->id)->count());
        $this->assertSame(0, ClinicalNote::where('child_profile_id', $child->id)->count());
        $this->assertNull(MediaAsset::find($media->id));
        Storage::disk('local')->assertMissing($media->path);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'child.erased_permanently',
            'auditable_id' => $child->id,
        ]);
    }
}
