<?php

namespace App\Services\Files\Scanners;

use App\Enums\FileScanStatus;

final readonly class ScanResult
{
    public function __construct(
        public FileScanStatus $status,
        public ?string $detail = null,
    ) {}
}
