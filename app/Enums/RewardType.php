<?php

namespace App\Enums;

enum RewardType: string
{
    case Participation = 'participation';
    case Effort = 'effort';
    case Milestone = 'milestone';
}
