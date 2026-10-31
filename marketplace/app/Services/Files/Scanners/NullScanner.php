<?php

namespace App\Services\Files\Scanners;

use App\Enums\FileScanStatus;

/**
 * Tarayıcı yapılandırılmamış ortamlar için. Geliştirme ve test ortamında dosyayı
 * temiz sayar; production'da asla temiz saymaz, dosya yönetici incelemesine kadar
 * "pending" kalır ve indirilemez.
 */
class NullScanner implements FileScanner
{
    public function name(): string
    {
        return 'none';
    }

    public function scan(string $localPath): ScanResult
    {
        if (app()->isProduction()) {
            return new ScanResult(FileScanStatus::Pending, 'Zararlı dosya tarayıcısı yapılandırılmamış.');
        }

        return new ScanResult(FileScanStatus::Clean, 'Geliştirme ortamı: tarama atlandı.');
    }
}
