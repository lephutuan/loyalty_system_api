<?php

declare(strict_types=1);

namespace App\Enum;

enum RedemptionStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Pending = 'pending';
}
