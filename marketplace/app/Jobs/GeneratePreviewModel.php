<?php

namespace App\Jobs;

use App\Enums\FileScanStatus;
use App\Exceptions\InvalidUploadException;
use App\Models\ProductFile;
use App\Services\Files\StlDecimator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creator önizleme yüklemediyse, taraması temiz çıkan STL dosyasından herkese açık,
 * sadeleştirilmiş bir önizleme üretir. Satılan dosyanın kendisi hiçbir zaman
 * public diske kopyalanmaz; yalnızca kaba ızgaraya indirgenmiş sürüm yazılır.
 */
class GeneratePreviewModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public ProductFile $file) {}

    public function handle(StlDecimator $decimator): void
    {
        $config = config('marketplace.storage.auto_preview');
        $file = $this->file->fresh();
        $product = $file?->product;

        if (! $config['enabled'] || $file === null || $product === null
            || $product->preview_model_path !== null
            || $file->extension !== 'stl'
            || ! $file->is_active
            || $file->scan_status !== FileScanStatus::Clean
            || $file->size_bytes > $config['max_source_mb'] * 1024 * 1024) {
            return;
        }

        $source = tempnam(sys_get_temp_dir(), 'pvs');
        $target = tempnam(sys_get_temp_dir(), 'pvt');

        try {
            $stream = Storage::disk($file->disk)->readStream($file->path);
            file_put_contents($source, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $result = $decimator->decimate($source, $target, $config['grid'], $config['max_triangles']);

            $disk = config('marketplace.storage.public_media_disk');
            $path = "products/{$product->uuid}/preview/".Str::random(32).'.stl';
            $output = fopen($target, 'rb');
            Storage::disk($disk)->put($path, $output, 'public');
            fclose($output);

            /** Bu sırada creator kendi önizlemesini yüklediyse onunki korunur. */
            $updated = $product->newQuery()->whereKey($product->id)->whereNull('preview_model_path')
                ->update(['preview_model_path' => $path, 'preview_auto_generated' => true]);

            if ($updated === 0) {
                Storage::disk($disk)->delete($path);
            }

            Log::info('Otomatik önizleme üretildi', ['product' => $product->uuid, ...$result]);
        } catch (InvalidUploadException $exception) {
            Log::notice('Otomatik önizleme üretilemedi', ['product' => $product->uuid, 'file' => $file->uuid, 'reason' => $exception->getMessage()]);
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }
}
