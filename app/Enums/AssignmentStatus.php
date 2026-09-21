<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Assigned = 'assigned';
    case Started = 'started';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Cancelled = 'cancelled';
}
