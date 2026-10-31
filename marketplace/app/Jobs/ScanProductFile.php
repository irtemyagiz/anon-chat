<?php

namespace App\Jobs;

use App\Enums\FileScanStatus;
use App\Models\ProductFile;
use App\Services\Audit\AuditLogger;
use App\Services\Files\Scanners\FileScanner;
use App\Services\Notifications\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ürün dosyasını zararlı yazılıma karşı tarar. HTTP isteği sırasında değil,
 * kuyrukta çalışır. Zararlı bulunan dosya pasifleştirilir ve güvenlik olayı kaydedilir.
 */
class ScanProductFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public ProductFile $file) {}

    public function handle(FileScanner $scanner, AuditLogger $audit): void
    {
        $disk = Storage::disk($this->file->disk);
        $temporary = null;

        try {
            $localPath = method_exists($disk, 'path') && is_file($disk->path($this->file->path))
                ? $disk->path($this->file->path)
                : $temporary = $this->downloadToTemporaryFile();

            $result = $scanner->scan($localPath);
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }

        $this->file->forceFill([
            'scan_status' => $result->status,
            'scanned_at' => now(),
            'is_active' => $result->status !== FileScanStatus::Infected && $this->file->is_active,
        ])->save();

        if ($result->status === FileScanStatus::Infected) {
            DB::table('security_events')->insert([
                'user_id' => $this->file->product->creator_id,
                'type' => 'file.infected',
                'severity' => 'high',
                'context' => json_encode(['product_file' => $this->file->uuid, 'scanner' => $scanner->name(), 'detail' => $result->detail]),
                'created_at' => now(),
            ]);

            $audit->record('product_file.infected', $this->file, null, ['scan_status' => 'infected'], context: ['detail' => $result->detail]);
            app(Notifier::class)->staff('products.moderate', __('Zararlı dosya tespit edildi'), __(':title ürününe yüklenen bir dosyada zararlı içerik bulundu ve dosya pasifleştirildi.', ['title' => $this->file->product->title]), route('admin.products.show', $this->file->product));
        }
    }

    private function downloadToTemporaryFile(): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'scan');
        file_put_contents($temporary, Storage::disk($this->file->disk)->readStream($this->file->path));

        return $temporary;
    }
}
