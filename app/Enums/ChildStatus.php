<?php

namespace App\Enums;

enum ChildStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
