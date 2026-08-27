<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Manifest;

interface ManifestScannerInterface
{
    public function scan(Manifest $manifest): void;
}
