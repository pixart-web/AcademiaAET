<?php

namespace App\Services\Screening;

use Carbon\CarbonInterface;

/**
 * Cronological age in completed years and months, and the age block a child
 * is routed to. Pure functions — no database, no clock of their own.
 */
class ScreeningAge
{
    /** Completed months between $birth and $on (never negative). */
    public static function months(CarbonInterface $birth, CarbonInterface $on): int
    {
        $months = ($on->year - $birth->year) * 12 + ($on->month - $birth->month);

        if ($on->day < $birth->day) {
            $months--;
        }

        return max(0, $months);
    }

    /** @return array{years: int, months: int} */
    public static function split(int $totalMonths): array
    {
        return ['years' => intdiv($totalMonths, 12), 'months' => $totalMonths % 12];
    }

    public static function label(int $totalMonths): string
    {
        ['years' => $y, 'months' => $m] = self::split($totalMonths);

        $years = $y.' '.($y === 1 ? 'ano' : 'anos');
        $months = $m.' '.($m === 1 ? 'mês' : 'meses');

        return "{$years} e {$months}";
    }

    /** '3' | '4' | '5' | '6-7', or null when outside 3a0m – 7a11m. */
    public static function block(int $totalMonths): ?string
    {
        foreach (config('screening.blocks') as $block => [$min, $max]) {
            if ($totalMonths >= $min && $totalMonths <= $max) {
                return (string) $block;
            }
        }

        return null;
    }
}
