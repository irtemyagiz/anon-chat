<?php

namespace App\Services\Files;

use App\Exceptions\InvalidUploadException;
use App\Models\FileFormat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Ürün dosyalarının sunucu tarafı doğrulaması.
 *
 * Tarayıcının bildirdiği MIME türüne güvenilmez. Sırasıyla: yönetim panelinde
 * izinli uzantı → boyut sınırı → sunucunun tespit ettiği MIME türü → dosya
 * içeriğinin gerçekten o biçimde olduğu → arşivlerde güvenlik kontrolleri.
 * Zararlı yazılım taraması bundan sonra kuyrukta (ScanProductFile) yapılır.
 */
class UploadInspector
{
    /** Arşiv içinde kabul edilmeyen, çalıştırılabilir veya betik dosya uzantıları. */
    private const FORBIDDEN_ARCHIVE_EXTENSIONS = [
        'exe', 'dll', 'bat', 'cmd', 'com', 'scr', 'msi', 'ps1', 'vbs', 'js', 'jse', 'wsf',
        'sh', 'php', 'phtml', 'phar', 'jar', 'apk', 'app', 'lnk', 'hta', 'cpl', 'reg',
    ];

    private const MAX_ARCHIVE_ENTRIES = 2000;

    private const MAX_COMPRESSION_RATIO = 100;

    private const MAX_ARCHIVE_UNCOMPRESSED_BYTES = 4 * 1024 * 1024 * 1024;

    /**
     * @param  list<string>|null  $allowedExtensions  Bu yükleme için ek kısıt (ör. önizleme: stl/obj/3mf)
     *
     * @throws InvalidUploadException
     */
    public function inspect(UploadedFile $file, ?array $allowedExtensions = null, ?int $maxBytes = null): InspectedFile
    {
        if (! $file->isValid()) {
            throw new InvalidUploadException(__('Dosya yüklenemedi. Lütfen tekrar deneyin.'));
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $format = FileFormat::query()->where('extension', $extension)->where('is_active', true)->first();

        if ($format === null || ($allowedExtensions !== null && ! in_array($extension, $allowedExtensions, true))) {
            throw new InvalidUploadException(__(':ext uzantılı dosyalar kabul edilmiyor.', ['ext' => strtoupper($extension ?: '?')]));
        }

        $maxBytes ??= (int) config('marketplace.storage.max_file_size_mb') * 1024 * 1024;
        $size = (int) $file->getSize();

        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidUploadException(__('Dosya boyutu en fazla :mb MB olabilir.', ['mb' => intdiv($maxBytes, 1024 * 1024)]));
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $format->mime_types, true)) {
            throw new InvalidUploadException(__(':name dosyasının içeriği :ext biçimiyle eşleşmiyor.', ['name' => $file->getClientOriginalName(), 'ext' => strtoupper($extension)]));
        }

        $path = $file->getRealPath();
        $this->assertContentMatches($extension, $path, $size);

        return new InspectedFile(
            extension: $extension,
            mimeType: $mime,
            sizeBytes: $size,
            sha256: hash_file('sha256', $path),
            originalName: $this->safeOriginalName($file->getClientOriginalName(), $extension),
        );
    }

    private function assertContentMatches(string $extension, string $path, int $size): void
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        $valid = match ($extension) {
            'stl' => $this->isStl($path, $head, $size),
            'obj' => $this->isText($head) && preg_match('/^\s*v\s+-?[\d.]/m', $head) === 1,
            '3mf' => $this->assertSafeArchive($path, requiredEntry: '3d/3dmodel.model'),
            'zip' => $this->assertSafeArchive($path),
            'rar' => str_starts_with($head, "Rar!\x1A\x07"),
            'pdf' => str_starts_with($head, '%PDF-'),
            'svg' => $this->isSafeSvg($path),
            'step', 'stp' => str_starts_with(ltrim($head), 'ISO-10303-21'),
            'dxf' => $this->isText($head) && preg_match('/^\s*0\s*\R\s*SECTION/m', $head) === 1,
            default => false,
        };

        if (! $valid) {
            throw new InvalidUploadException(__('Dosya içeriği :ext biçiminde değil veya bozuk.', ['ext' => strtoupper($extension)]));
        }

        if ($this->isText($head) && preg_match('/<\?php|<script\b/i', $head) === 1) {
            throw new InvalidUploadException(__('Dosya izin verilmeyen kod içeriyor.'));
        }
    }

    /**
     * İkili STL: 80 bayt başlık + 4 bayt üçgen sayısı + üçgen başına 50 bayt.
     * ASCII STL: "solid" ile başlar ve "facet" içerir.
     */
    private function isStl(string $path, string $head, int $size): bool
    {
        if ($size >= 84) {
            $triangles = unpack('V', substr($head, 80, 4))[1] ?? -1;

            if ($triangles > 0 && $size === 84 + $triangles * 50) {
                return true;
            }
        }

        return str_starts_with(ltrim($head), 'solid') && str_contains($head, 'facet');
    }

    private function isText(string $head): bool
    {
        return ! str_contains($head, "\0");
    }

    /**
     * SVG'ler indirilebilir dosya olarak sunulsa da tarayıcıda açıldığında betik
     * çalıştırabilir. Betik, olay işleyicisi, harici varlık ve gömülü HTML reddedilir.
     */
    private function isSafeSvg(string $path): bool
    {
        $content = (string) file_get_contents($path);

        if (preg_match('/<svg\b/i', $content) !== 1) {
            return false;
        }

        if (preg_match('/<!ENTITY|<!DOCTYPE|<script\b|<foreignObject\b|\son[a-z]+\s*=|javascript:|data:text\/html/i', $content) === 1) {
            throw new InvalidUploadException(__('SVG dosyası güvenlik nedeniyle betik veya etkileşimli içerik barındıramaz.'));
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($content, options: LIBXML_NONET);
        libxml_use_internal_errors($previous);

        return $document !== false;
    }

    /**
     * ZIP/3MF arşiv güvenliği: giriş sayısı, dizin dışına çıkma, şifreli giriş,
     * sıkıştırma bombası ve çalıştırılabilir dosya kontrolleri.
     */
    private function assertSafeArchive(string $path, ?string $requiredEntry = null): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        try {
            if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new InvalidUploadException(__('Arşivdeki dosya sayısı izin verilen sınırın dışında.'));
            }

            $totalUncompressed = 0;
            $foundRequired = $requiredEntry === null;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
                $name = str_replace('\\', '/', (string) $entry['name']);

                if (str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('/(^|\/)\.\.(\/|$)|^[a-zA-Z]:/', $name) === 1) {
                    throw new InvalidUploadException(__('Arşiv, klasör dışına çıkan dosya yolları içeriyor.'));
                }

                if (($entry['encryption_method'] ?? 0) !== 0) {
                    throw new InvalidUploadException(__('Şifreli arşivler güvenlik taraması yapılamadığı için kabul edilmiyor.'));
                }

                if (in_array(Str::lower(pathinfo($name, PATHINFO_EXTENSION)), self::FORBIDDEN_ARCHIVE_EXTENSIONS, true)) {
                    throw new InvalidUploadException(__('Arşiv çalıştırılabilir dosya içeriyor: :name', ['name' => basename($name)]));
                }

                $compressed = max(1, (int) $entry['comp_size']);
                $uncompressed = (int) $entry['size'];
                $totalUncompressed += $uncompressed;

                if ($uncompressed > 1024 * 1024 && intdiv($uncompressed, $compressed) > self::MAX_COMPRESSION_RATIO) {
                    throw new InvalidUploadException(__('Arşiv olağan dışı sıkıştırma oranı içeriyor ve reddedildi.'));
                }

                if ($requiredEntry !== null && Str::lower($name) === $requiredEntry) {
                    $foundRequired = true;
                }
            }

            if ($totalUncompressed > self::MAX_ARCHIVE_UNCOMPRESSED_BYTES) {
                throw new InvalidUploadException(__('Arşivin açılmış boyutu çok büyük.'));
            }

            return $foundRequired;
        } finally {
            $zip->close();
        }
    }

    /**
     * İndirme sırasında kullanılacak görünen ad. Diskteki dosya adı her zaman rastgeledir.
     */
    private function safeOriginalName(string $name, string $extension): string
    {
        $base = Str::of(pathinfo($name, PATHINFO_FILENAME))->ascii()->replaceMatches('/[^A-Za-z0-9._-]+/', '-')->trim('-.')->limit(120, '');

        return ($base->isEmpty() ? 'model' : $base->toString()).'.'.$extension;
    }
}
