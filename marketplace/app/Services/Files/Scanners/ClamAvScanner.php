<?php

namespace App\Services\Files\Scanners;

use App\Enums\FileScanStatus;
use Illuminate\Support\Facades\Process;

/**
 * ClamAV ile tarama. Sunucuda clamd çalışıyorsa "clamdscan", yoksa "clamscan" kullanılır.
 * Çıkış kodları: 0 = temiz, 1 = zararlı bulundu, 2 = hata.
 */
class ClamAvScanner implements FileScanner
{
    public function __construct(private readonly string $binary = 'clamdscan', private readonly int $timeout = 300) {}

    public function name(): string
    {
        return 'clamav';
    }

    public function scan(string $localPath): ScanResult
    {
        $result = Process::timeout($this->timeout)->run([$this->binary, '--no-summary', '--stdout', $localPath]);

        return match ($result->exitCode()) {
            0 => new ScanResult(FileScanStatus::Clean),
            1 => new ScanResult(FileScanStatus::Infected, trim($result->output())),
            default => new ScanResult(FileScanStatus::Failed, trim($result->errorOutput() ?: $result->output())),
        };
    }
}
