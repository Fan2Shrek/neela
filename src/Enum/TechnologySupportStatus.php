<?php

declare(strict_types=1);

namespace App\Enum;

enum TechnologySupportStatus: string
{
    case UP_TO_DATE = 'up_to_date';
    case LTS = 'lts';
    // Distinct value from the dependency-level "outdated" badge (a different color:
    // aging-but-supported here, versus fully-behind-constraint there).
    case OUTDATED = 'technology_outdated';
    case END_OF_LIFE = 'end_of_life';
    case UNKNOWN = 'unknown';
}
