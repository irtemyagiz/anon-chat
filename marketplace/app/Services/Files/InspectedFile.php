<?php

namespace App\Services\Files;

final readonly class InspectedFile
{
    public function __construct(
        public string $extension,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public string $originalName,
    ) {}
}
