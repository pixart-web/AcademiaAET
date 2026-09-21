<?php

namespace App\Services;

use App\Enums\RewardType;
use App\Models\Attempt;
use App\Models\Evaluation;
use App\Models\RewardEvent;

class RewardService
{
    private const PARTICIPATION_POINTS = 10;

    private const EFFORT_POINTS = 5;

    private const MILESTONE_POINTS = 25;

    private const MILESTONE_SCORE_THRESHOLD = 80;

    /**
     * Awarded once per attempt no matter how many times the submit endpoint is
     * retried — dedupe_key is unique on (attempt, type), so a resend after a
     * dropped network response can never mint a second reward.
     */
    public function awardParticipation(Attempt $attempt): void
    {
        $this->award($attempt, RewardType::Participation, self::PARTICIPATION_POINTS);
    }

    public function awardForEvaluation(Evaluation $evaluation): void
    {
        $attempt = $evaluation->attempt;

        $this->award($attempt, RewardType::Effort, self::EFFORT_POINTS);

        if ($evaluation->score !== null && (float) $evaluation->score >= self::MILESTONE_SCORE_THRESHOLD) {
            $this->award($attempt, RewardType::Milestone, self::MILESTONE_POINTS);
        }
    }

    private function award(Attempt $attempt, RewardType $type, int $points): void
    {
        RewardEvent::query()->firstOrCreate(
            ['dedupe_key' => RewardEvent::dedupeKeyFor($attempt->id, $type)],
            [
                'child_profile_id' => $attempt->assignment->child_profile_id,
                'attempt_id' => $attempt->id,
                'type' => $type,
                'points' => $points,
                'awarded_at' => now(),
            ],
        );
    }
}
