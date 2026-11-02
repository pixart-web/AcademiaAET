<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AET-RC01 finding 1/8: read-only reconciliation report for media the
 * migration backfill couldn't classify, or whose file has gone missing.
 */
class MediaReconcileOrphansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_unclassified_media_without_deleting_anything(): void
    {
        $org = Organization::factory()->create();
        $unclassified = MediaAsset::factory()->for($org)->create(['purpose' => null]);

        $this->artisan('media:reconcile-orphans')
            ->expectsOutputToContain((string) $unclassified->id)
            ->assertExitCode(0);

        $this->assertNotNull(MediaAsset::find($unclassified->id));
    }

    public function test_reports_no_issues_when_everything_is_classified(): void
    {
        $org = Organization::factory()->create();
        MediaAsset::factory()->for($org)->create(['purpose' => 'instructional']);

        $this->artisan('media:reconcile-orphans')
            ->expectsOutputToContain('nothing needs manual classification')
            ->assertExitCode(0);
    }

    public function test_check_files_flag_reports_a_missing_file(): void
    {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $media = MediaAsset::factory()->for($org)->create(['purpose' => 'instructional', 'status' => 'active']);
        // Deliberately never put the file — simulates a row whose file
        // was lost on disk without the database knowing.

        $this->artisan('media:reconcile-orphans --check-files')
            ->expectsOutputToContain((string) $media->id)
            ->assertExitCode(0);
    }
}
