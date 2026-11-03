<?php

namespace App\Services\Screening;

use App\Enums\ScreeningItemKind;
use App\Enums\ScreeningOutcome;
use App\Models\ScreeningSession;

/**
 * Turns a session's responses into the numbers and the automatic conclusion
 * of the report. Deterministic and side-effect free so it can be tested to
 * the decimal; every threshold comes from config/screening.php.
 */
class ScreeningScorer
{
    /**
     * @return array{
     *   areas: array<string, array{points: int, applicable: int, not_applied: int, percent: float|null}>,
     *   total: array{points: int, applicable: int, not_applied: int, percent: float|null},
     *   findings: list<array<string, mixed>>,
     *   attention: bool,
     *   attention_reasons: list<string>,
     *   conclusion: string
     * }
     */
    public function summarize(ScreeningSession $session): array
    {
        $responses = $session->responses()->with('item')->get();
        $countZero = (bool) config('screening.count_not_applied_as_zero');

        $buckets = [];
        foreach (['speech', 'comprehension', 'expression'] as $area) {
            $buckets[$area] = ['points' => 0, 'applicable' => 0, 'not_applied' => 0];
        }

        foreach ($responses as $response) {
            $area = $response->item->kind->area();

            if ($response->outcome === ScreeningOutcome::NotApplied) {
                $buckets[$area]['not_applied']++;

                continue;
            }

            $buckets[$area]['applicable']++;
            $buckets[$area]['points'] += (int) $response->points;
        }

        $areas = array_map(fn (array $b) => $this->withPercent($b, $countZero), $buckets);

        $total = $this->withPercent([
            'points' => array_sum(array_column($buckets, 'points')),
            'applicable' => array_sum(array_column($buckets, 'applicable')),
            'not_applied' => array_sum(array_column($buckets, 'not_applied')),
        ], $countZero);

        $findings = $this->phoneticFindings($responses, $session->age_months);

        $reasons = [];
        if ($total['percent'] !== null && $total['percent'] < config('screening.attention_threshold') * 100) {
            $reasons[] = 'score_below_threshold';
        }
        if (collect($findings)->contains(fn ($f) => $f['expected_for_age'])) {
            $reasons[] = 'phonetic_error_in_expected_sound';
        }

        $attention = $reasons !== [];

        return [
            'areas' => $areas,
            'total' => $total,
            'findings' => $findings,
            'attention' => $attention,
            'attention_reasons' => $reasons,
            'conclusion' => config($attention ? 'screening.conclusions.attention' : 'screening.conclusions.expected'),
        ];
    }

    /** @param array{points: int, applicable: int, not_applied: int} $b */
    private function withPercent(array $b, bool $countNotAppliedAsZero): array
    {
        $denominator = $b['applicable'] + ($countNotAppliedAsZero ? $b['not_applied'] : 0);

        return $b + ['percent' => $denominator > 0 ? round($b['points'] / $denominator * 100, 1) : null];
    }

    /**
     * Altered/omitted sounds from the speech block, each flagged when the
     * sound should already be acquired at the child's chronological age.
     */
    private function phoneticFindings($responses, int $ageMonths): array
    {
        return $responses
            ->filter(fn ($r) => $r->item->kind === ScreeningItemKind::Speech && $r->outcome->isPhoneticError())
            ->map(function ($r) use ($ageMonths) {
                $norm = $r->item->phonemeNorm();
                $expectedBy = $norm['expected_by_months'] ?? null;

                return [
                    'word' => $r->item->target_word,
                    'phoneme' => $r->item->phoneme,
                    'phoneme_label' => $norm['label'] ?? $r->item->phoneme,
                    'outcome' => $r->outcome->value,
                    'outcome_label' => $r->outcome->label(),
                    'produced_sound' => $r->produced_sound,
                    'expected_by_months' => $expectedBy,
                    'expected_for_age' => $expectedBy !== null && $ageMonths > $expectedBy,
                ];
            })
            ->values()
            ->all();
    }
}
