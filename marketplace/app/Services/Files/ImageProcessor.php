<?php

namespace App\Services\Files;

use App\Exceptions\InvalidUploadException;
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
     * @return array{path: string, width: int, height: int}
     */
    public function store(UploadedFile $file, string $disk, string $directory, int $maxEdge = self::MAX_EDGE): array
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

        $useWebp = function_exists('imagewebp');

        ob_start();
        $useWebp ? imagewebp($canvas, null, 82) : imagejpeg($canvas, null, 85);
        $binary = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        $path = trim($directory, '/').'/'.Str::random(32).($useWebp ? '.webp' : '.jpg');
        Storage::disk($disk)->put($path, $binary, 'public');

        return ['path' => $path, 'width' => $targetWidth, 'height' => $targetHeight];
    }
}
