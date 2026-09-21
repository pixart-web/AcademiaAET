<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActivityVersioningService
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function createInitialVersion(Activity $activity, User $author, string $title, ?string $instructions, ?string $evaluationCriteria, array $steps): ActivityVersion
    {
        return DB::transaction(function () use ($activity, $author, $title, $instructions, $evaluationCriteria, $steps) {
            $version = $activity->versions()->create([
                'version_number' => 1,
                'title' => $title,
                'instructions' => $instructions,
                'evaluation_criteria' => $evaluationCriteria,
                'created_by_user_id' => $author->id,
            ]);

            $this->replaceSteps($version, $steps);

            $activity->update(['current_version_id' => $version->id]);

            return $version->fresh('steps');
        });
    }

    /**
     * A version already referenced by any assignment is immutable: editing it
     * always forks a new version rather than mutating history a child already
     * attempted against.
     */
    public function isEditableInPlace(ActivityVersion $version): bool
    {
        return $version->published_at === null && $version->assignments()->doesntExist();
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    public function updateOrFork(ActivityVersion $version, User $author, string $title, ?string $instructions, ?string $evaluationCriteria, array $steps): ActivityVersion
    {
        return DB::transaction(function () use ($version, $author, $title, $instructions, $evaluationCriteria, $steps) {
            if ($this->isEditableInPlace($version)) {
                $version->update([
                    'title' => $title,
                    'instructions' => $instructions,
                    'evaluation_criteria' => $evaluationCriteria,
                ]);
                $this->replaceSteps($version, $steps);

                return $version->fresh('steps');
            }

            $activity = $version->activity;

            $newVersion = $activity->versions()->create([
                'version_number' => $activity->nextVersionNumber(),
                'title' => $title,
                'instructions' => $instructions,
                'evaluation_criteria' => $evaluationCriteria,
                'created_by_user_id' => $author->id,
            ]);

            $this->replaceSteps($newVersion, $steps);

            $activity->update(['current_version_id' => $newVersion->id]);

            return $newVersion->fresh('steps');
        });
    }

    public function publish(ActivityVersion $version): void
    {
        $version->update(['published_at' => now()]);
        $version->activity->update(['status' => 'published']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function replaceSteps(ActivityVersion $version, array $steps): void
    {
        $version->steps()->delete();

        foreach ($steps as $index => $step) {
            $version->steps()->create([
                'position' => $index + 1,
                'title' => $step['title'] ?? null,
                'body' => $step['body'] ?? null,
                'instruction_media_asset_id' => $step['instruction_media_asset_id'] ?? null,
                'response_type' => $step['response_type'],
                'response_config' => $step['response_config'] ?? null,
            ]);
        }
    }
}
