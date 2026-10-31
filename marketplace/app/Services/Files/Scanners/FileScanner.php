<?php

namespace App\Services\Files\Scanners;

interface FileScanner
{
    public function name(): string;

    /**
     * Yerel dosya yolundaki dosyayı tarar.
     */
    public function scan(string $localPath): ScanResult;
}
