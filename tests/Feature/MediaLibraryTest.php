<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: MediaAssetPolicy was missing viewAny(), so every
     * visit to the content library returned 403 for every user — found by
     * actually loading the page in a browser, not by reading the code.
     */
    public function test_professional_can_view_the_media_library(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $this->actingAs($pro)->get(route('media.index'))->assertOk();
    }

    public function test_professional_can_upload_an_image_with_alt_text(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $file = UploadedFile::fake()->image('foto.png', 100, 100);

        $this->actingAs($pro)->post(route('media.store'), [
            'kind' => 'image',
            'title' => 'Foto de teste',
            'alt_text' => 'Uma criança a sorrir, exemplo de demonstração.',
            'file' => $file,
        ])->assertRedirect();

        $this->assertDatabaseHas('media_assets', ['title' => 'Foto de teste', 'organization_id' => $org->id]);
    }

    public function test_upload_without_alt_text_is_rejected_for_image(): void
    {
        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $file = UploadedFile::fake()->image('foto.png', 100, 100);

        $this->actingAs($pro)->post(route('media.store'), [
            'kind' => 'image',
            'title' => 'Sem alt',
            'file' => $file,
        ])->assertSessionHasErrors('alt_text');

        $this->assertDatabaseMissing('media_assets', ['title' => 'Sem alt']);
    }

    /**
     * Upload size limits were previously hardcoded constants in the
     * controller; now they're read from config/media.php (overridable via
     * MEDIA_MAX_*_KB env vars). This confirms the validation rule really
     * follows a lowered config value, not a leftover hardcoded number.
     */
    public function test_upload_size_limit_is_read_from_config(): void
    {
        config(['media.max_size_kb.image' => 1]);

        $org = Organization::factory()->create();
        $pro = User::factory()->for($org)->create();

        $file = UploadedFile::fake()->image('foto.png', 100, 100)->size(50);

        $this->actingAs($pro)->post(route('media.store'), [
            'kind' => 'image',
            'title' => 'Excede o limite',
            'alt_text' => 'Exemplo de demonstração.',
            'file' => $file,
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('media_assets', ['title' => 'Excede o limite']);
    }

    public function test_professional_from_another_organization_cannot_archive_media(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->for($otherOrg)->create();
        $media = MediaAsset::factory()->for($org)->create();

        $this->actingAs($outsider)->delete(route('media.destroy', $media))->assertForbidden();
    }
}
