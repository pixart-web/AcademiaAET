<?php

namespace App\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Document = 'document';
}
