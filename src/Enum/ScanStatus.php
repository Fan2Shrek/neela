<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ScanStatus: string implements TranslatableInterface
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('scan_status.'.$this->value, locale: $locale);
    }
}
