<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectUpdateStatus: string
{
    case UP_TO_DATE = 'up_to_date';
    case PARTIALLY_UP_TO_DATE = 'partially_up_to_date';
    case OUTDATED = 'outdated';
}
