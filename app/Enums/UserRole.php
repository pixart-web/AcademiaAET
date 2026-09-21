<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Professional = 'professional';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Professional => 'Terapeuta',
            self::Guardian => 'Encarregado de educação',
        };
    }
}
