<?php

namespace Database\Seeders;

use App\Models\FileFormat;
use Illuminate\Database\Seeder;

class FileFormatSeeder extends Seeder
{
    /**
     * @var array<string, array{label: string, mime: list<string>, archive?: bool, preview?: bool}>
     */
    private const FORMATS = [
        'stl' => ['label' => 'STL', 'mime' => ['model/stl', 'application/sla', 'application/vnd.ms-pki.stl', 'application/octet-stream', 'text/plain'], 'preview' => true],
        '3mf' => ['label' => '3MF', 'mime' => ['model/3mf', 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml', 'application/zip', 'application/octet-stream'], 'preview' => true],
        'obj' => ['label' => 'OBJ', 'mime' => ['model/obj', 'text/plain', 'application/octet-stream'], 'preview' => true],
        'step' => ['label' => 'STEP', 'mime' => ['model/step', 'application/step', 'text/plain', 'application/octet-stream']],
        'stp' => ['label' => 'STP', 'mime' => ['model/step', 'application/step', 'text/plain', 'application/octet-stream']],
        'zip' => ['label' => 'ZIP', 'mime' => ['application/zip', 'application/x-zip-compressed'], 'archive' => true],
        'rar' => ['label' => 'RAR', 'mime' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/x-rar'], 'archive' => true],
        'pdf' => ['label' => 'PDF', 'mime' => ['application/pdf']],
        'svg' => ['label' => 'SVG', 'mime' => ['image/svg+xml', 'text/xml', 'text/plain']],
        'dxf' => ['label' => 'DXF', 'mime' => ['image/vnd.dxf', 'application/dxf', 'text/plain', 'application/octet-stream']],
    ];

    public function run(): void
    {
        foreach (self::FORMATS as $extension => $format) {
            FileFormat::query()->updateOrCreate(['extension' => $extension], [
                'label' => $format['label'],
                'mime_types' => $format['mime'],
                'is_archive' => $format['archive'] ?? false,
                'is_previewable' => $format['preview'] ?? false,
                'is_active' => true,
            ]);
        }
    }
}
