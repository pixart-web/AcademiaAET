<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Defense-in-depth against a concurrent double `startOrResume()`: even if
 * application-level locking (AttemptService::startOrResume) were ever
 * bypassed or raced, the database itself refuses a second 'in_progress'
 * attempt per assignment. A partial unique index (Postgres-specific —
 * this project only targets Postgres, see docker-compose.yml) rather than
 * a plain unique column, since multiple *submitted* attempts for the same
 * assignment are legitimate.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX one_in_progress_attempt_per_assignment '.
            "ON attempts (assignment_id) WHERE status = 'in_progress'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS one_in_progress_attempt_per_assignment');
    }
};
