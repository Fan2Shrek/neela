<?php

declare(strict_types=1);

namespace App\Enum;

enum ScanStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
