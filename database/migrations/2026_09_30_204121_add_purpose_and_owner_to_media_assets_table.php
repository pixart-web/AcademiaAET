<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AET-RC01 finding 1: media_assets had no way to tell a professional's
 * didactic upload apart from a child's clinical recording/drawing — both
 * went through the same table, the same organization-wide library listing,
 * and the same "same organization" check in MediaStreamController. This
 * adds the distinction and backfills existing rows from what can still be
 * determined:
 *
 *  1. Any row referenced by step_responses.media_asset_id is a clinical
 *     response — its owner is the child of the attempt that recorded it.
 *  2. Anything else with a known uploader (uploaded_by_user_id) is
 *     instructional — it can only have been created through
 *     MediaAssetController's professional upload flow.
 *  3. Anything left over (should be rare/none in practice, but the
 *     column existed before this distinction did) is left with a NULL
 *     purpose — deliberately NOT deleted, NOT guessed at, and NOT made
 *     available through the general library: MediaAssetPolicy treats a
 *     NULL purpose the same as an unowned clinical response, visible only
 *     to an admin for manual review.
 *
 * The backfill is written as plain query-builder reads/writes (not a
 * single database-specific raw statement) so it runs identically under
 * the SQLite used by the fast test suite and the PostgreSQL used in
 * development/production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('purpose', 32)->nullable()->after('kind');
            $table->foreignId('owner_child_profile_id')->nullable()->after('purpose')
                ->constrained('child_profiles')->nullOnDelete();
        });

        DB::table('step_responses')
            ->join('attempts', 'attempts.id', '=', 'step_responses.attempt_id')
            ->join('assignments', 'assignments.id', '=', 'attempts.assignment_id')
            ->whereNotNull('step_responses.media_asset_id')
            ->select('step_responses.media_asset_id', 'assignments.child_profile_id')
            ->get()
            ->each(function ($row) {
                DB::table('media_assets')->where('id', $row->media_asset_id)->update([
                    'purpose' => 'clinical_response',
                    'owner_child_profile_id' => $row->child_profile_id,
                ]);
            });

        DB::table('media_assets')
            ->whereNull('purpose')
            ->whereNotNull('uploaded_by_user_id')
            ->update(['purpose' => 'instructional']);

        Schema::table('media_assets', function (Blueprint $table) {
            $table->index(['organization_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'purpose', 'status']);
            $table->dropConstrainedForeignId('owner_child_profile_id');
            $table->dropColumn('purpose');
        });
    }
};
