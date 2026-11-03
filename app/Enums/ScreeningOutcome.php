<?php

namespace App\Enums;

enum ScreeningOutcome: string
{
    case Correct = 'correct';
    case Substitution = 'substitution';
    case Omission = 'omission';
    case Distortion = 'distortion';
    case Incorrect = 'incorrect';
    case NotApplied = 'not_applied';

    public function points(): ?int
    {
        return match ($this) {
            self::Correct => 1,
            self::NotApplied => null,
            default => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Correct => 'Correto',
            self::Substitution => 'Substituição',
            self::Omission => 'Omissão',
            self::Distortion => 'Distorção',
            self::Incorrect => 'Incorreto',
            self::NotApplied => 'Não aplicado',
        };
    }

    public function isPhoneticError(): bool
    {
        return in_array($this, [self::Substitution, self::Omission, self::Distortion], true);
    }
}
