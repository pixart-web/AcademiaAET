<?php

namespace App\Enums;

enum ScreeningItemKind: string
{
    case Speech = 'speech';
    case Comprehension = 'comprehension';
    case Naming = 'naming';
    case Repetition = 'repetition';

    /** Order the subsections are applied in. */
    public function order(): int
    {
        return match ($this) {
            self::Speech => 1,
            self::Comprehension => 2,
            self::Naming => 3,
            self::Repetition => 4,
        };
    }

    /** speech | comprehension | expression — the three areas of the report. */
    public function area(): string
    {
        return match ($this) {
            self::Speech => 'speech',
            self::Comprehension => 'comprehension',
            self::Naming, self::Repetition => 'expression',
        };
    }

    /** Whether the answer is validated by the app itself (tap on a picture). */
    public function isAutoValidated(): bool
    {
        return $this === self::Comprehension;
    }
}
