<?php

namespace App\Enums;

enum VisualExperience: string
{
    case Early = '3-6';
    case Middle = '7-13';
    case Teen = '14-18';

    public static function suggestedFor(int $ageInYears): self
    {
        return match (true) {
            $ageInYears <= 6 => self::Early,
            $ageInYears <= 13 => self::Middle,
            default => self::Teen,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Early => '3–6 anos',
            self::Middle => '7–13 anos',
            self::Teen => '14–18 anos',
        };
    }
}
