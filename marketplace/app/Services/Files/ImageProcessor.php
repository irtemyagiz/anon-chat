<?php

namespace App\Services\Files;

use App\Exceptions\InvalidUploadException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Yüklenen görselleri GD ile yeniden kodlar.
 *
 * Yeniden kodlama; EXIF/konum bilgisini siler, görsel içine gizlenmiş içerikleri
 * (polyglot dosyalar) temizler, boyutu sınırlar ve WebP'ye çevirir (GD WebP
 * desteklemiyorsa JPEG).
 */
class ImageProcessor
{
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    private const MAX_SOURCE_PIXELS = 40_000_000;

    private const MAX_EDGE = 2400;

    /**
     * Ürün görselleri için üretilen küçük boyutlar (genişlik, piksel). Liste ve kartlarda
     * srcset ile kullanılır; dosya adı ana görselden türetilir (bkz. variantPath).
     *
     * @var list<int>
     */
    public const PRODUCT_VARIANTS = [480, 960];

    /**
     * @param  list<int>  $variantWidths  Ek olarak üretilecek genişlikler. Ana görselden
     *                                    büyük olanlar ana görselin kopyası olarak yazılır, böylece yol her zaman vardır.
     * @return array{path: string, width: int, height: int, variants: array<int, string>}
     */
    public function store(UploadedFile $file, string $disk, string $directory, int $maxEdge = self::MAX_EDGE, array $variantWidths = []): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new InvalidUploadException(__('Görsel en fazla 10 MB olabilir.'));
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidUploadException(__('Yalnızca JPG, PNG veya WebP görseller kabul edilir.'));
        }

        [$width, $height] = $info;

        if ($width * $height > self::MAX_SOURCE_PIXELS) {
            throw new InvalidUploadException(__('Görsel çözünürlüğü çok yüksek.'));
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new InvalidUploadException(__('Görsel okunamadı veya bozuk.'));
        }

        $scale = min(1, $maxEdge / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        imagedestroy($source);

        $path = trim($directory, '/').'/'.Str::random(32).(function_exists('imagewebp') ? '.webp' : '.jpg');
        $binary = $this->encode($canvas);
        Storage::disk($disk)->put($path, $binary, 'public');

        $variants = [];

        foreach ($variantWidths as $variantWidth) {
            $variants[$variantWidth] = self::variantPath($path, $variantWidth);
            Storage::disk($disk)->put($variants[$variantWidth], $variantWidth < $targetWidth ? $this->encode($this->resize($canvas, $variantWidth)) : $binary, 'public');
        }

        imagedestroy($canvas);

        return ['path' => $path, 'width' => $targetWidth, 'height' => $targetHeight, 'variants' => $variants];
    }

    /**
     * Var olan bir görselden eksik küçük boyutları üretir (ör. yeni bir boyut eklendiğinde).
     *
     * @param  list<int>  $variantWidths
     * @return int Üretilen dosya sayısı
     */
    public function backfillVariants(string $disk, string $path, array $variantWidths): int
    {
        $storage = Storage::disk($disk);
        $missing = array_values(array_filter($variantWidths, fn (int $width) => ! $storage->exists(self::variantPath($path, $width))));

        if ($missing === [] || ! $storage->exists($path)) {
            return 0;
        }

        $canvas = @imagecreatefromstring((string) $storage->get($path));

        if ($canvas === false) {
            return 0;
        }

        foreach ($missing as $width) {
            $storage->put(self::variantPath($path, $width), $width < imagesx($canvas) ? $this->encode($this->resize($canvas, $width)) : (string) $storage->get($path), 'public');
        }

        imagedestroy($canvas);

        return count($missing);
    }

    /**
     * "products/x/abc.webp" + 480 → "products/x/abc-480.webp"
     */
    public static function variantPath(string $path, int $width): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return substr($path, 0, -strlen($extension) - 1)."-{$width}.{$extension}";
    }

    private function resize(GdImage $canvas, int $width): GdImage
    {
        $height = max(1, (int) round(imagesy($canvas) * $width / imagesx($canvas)));
        $resized = imagecreatetruecolor($width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $canvas, 0, 0, 0, 0, $width, $height, imagesx($canvas), imagesy($canvas));

        return $resized;
    }

    private function encode(GdImage $canvas): string
    {
        ob_start();
        function_exists('imagewebp') ? imagewebp($canvas, null, 82) : imagejpeg($canvas, null, 85);

        return (string) ob_get_clean();
    }
}
