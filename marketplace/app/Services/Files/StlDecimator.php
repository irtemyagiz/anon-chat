<?php

namespace App\Services\Files;

use App\Exceptions\InvalidUploadException;
use Generator;

/**
 * STL modelinden herkese açık, düşük çözünürlüklü önizleme üretir (vertex clustering).
 *
 * Model, en uzun kenarı $grid hücreye bölünen bir ızgaraya oturtulur; aynı hücreye düşen
 * köşeler hücre ortalamasında birleştirilir ve çökmüş üçgenler atılır. Böylece önizleme
 * baskı kalitesinde değildir: hücreden küçük ayrıntılar kaybolur. Dosya iki kez akış
 * olarak okunur; bellek kullanımı giriş boyutuna değil çıkış üçgen sayısına bağlıdır.
 */
class StlDecimator
{
    private const CHUNK_TRIANGLES = 4096;

    /**
     * @return array{triangles_in: int, triangles_out: int, grid: int}
     *
     * @throws InvalidUploadException
     */
    public function decimate(string $sourcePath, string $targetPath, int $grid = 80, int $maxTriangles = 60_000): array
    {
        [$min, $max, $trianglesIn] = $this->bounds($sourcePath);
        $longest = max($max[0] - $min[0], $max[1] - $min[1], $max[2] - $min[2]);

        if ($trianglesIn === 0 || $longest <= 0.0) {
            throw new InvalidUploadException(__('STL dosyasında geçerli üçgen bulunamadı.'));
        }

        /** Çıkış üçgen sınırını aşarsa ızgara %25 kabalaştırılarak yeniden denenir. */
        while (($result = $this->cluster($sourcePath, $min, $longest / $grid, $grid, $maxTriangles)) === null) {
            $grid = (int) floor($grid * 0.75);

            if ($grid < 8) {
                throw new InvalidUploadException(__('Model önizleme için sadeleştirilemedi.'));
            }
        }

        [$cells, $triangles] = $result;
        $this->write($targetPath, $cells, $triangles);

        return ['triangles_in' => $trianglesIn, 'triangles_out' => count($triangles), 'grid' => $grid];
    }

    /**
     * @return array{0: array{float, float, float}, 1: array{float, float, float}, 2: int}
     */
    private function bounds(string $path): array
    {
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $count = 0;

        foreach ($this->triangles($path) as $triangle) {
            $count++;

            for ($vertex = 0; $vertex < 9; $vertex += 3) {
                for ($axis = 0; $axis < 3; $axis++) {
                    $value = $triangle[$vertex + $axis];

                    if (! is_finite($value)) {
                        throw new InvalidUploadException(__('STL dosyası eksik veya bozuk.'));
                    }

                    $min[$axis] = min($min[$axis], $value);
                    $max[$axis] = max($max[$axis], $value);
                }
            }
        }

        return [$min, $max, $count];
    }

    /**
     * @param  array{float, float, float}  $min
     * @return array{0: array<int, array{float, float, float, int}>, 1: array<string, array{int, int, int}>}|null Üçgen sınırı aşılırsa null
     */
    private function cluster(string $path, array $min, float $cellSize, int $grid, int $maxTriangles): ?array
    {
        $stride = $grid + 1;
        $cells = [];
        $triangles = [];

        foreach ($this->triangles($path) as $t) {
            $keys = [];

            for ($vertex = 0; $vertex < 9; $vertex += 3) {
                $ix = min($grid, (int) (($t[$vertex] - $min[0]) / $cellSize));
                $iy = min($grid, (int) (($t[$vertex + 1] - $min[1]) / $cellSize));
                $iz = min($grid, (int) (($t[$vertex + 2] - $min[2]) / $cellSize));
                $key = $ix + $iy * $stride + $iz * $stride * $stride;
                $keys[] = $key;

                if (isset($cells[$key])) {
                    $cells[$key][0] += $t[$vertex];
                    $cells[$key][1] += $t[$vertex + 1];
                    $cells[$key][2] += $t[$vertex + 2];
                    $cells[$key][3]++;
                } else {
                    $cells[$key] = [$t[$vertex], $t[$vertex + 1], $t[$vertex + 2], 1];
                }
            }

            [$a, $b, $c] = $keys;

            if ($a === $b || $b === $c || $a === $c) {
                continue;
            }

            /** Yönü (sarım sırasını) koruyarak en küçük anahtarı başa al; aynı üçgen tekrar eklenmez. */
            $canonical = match (min($a, $b, $c)) {
                $a => [$a, $b, $c],
                $b => [$b, $c, $a],
                default => [$c, $a, $b],
            };
            $triangles[implode(',', $canonical)] = $canonical;

            if (count($triangles) > $maxTriangles) {
                return null;
            }
        }

        return [$cells, $triangles];
    }

    /**
     * @param  array<int, array{float, float, float, int}>  $cells
     * @param  array<string, array{int, int, int}>  $triangles
     */
    private function write(string $path, array $cells, array $triangles): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new InvalidUploadException(__('Önizleme dosyası yazılamadı.'));
        }

        fwrite($handle, str_pad('Katman preview (decimated)', 80, ' '));
        fwrite($handle, pack('V', count($triangles)));

        $point = function (int $key) use ($cells): array {
            [$x, $y, $z, $n] = $cells[$key];

            return [$x / $n, $y / $n, $z / $n];
        };

        foreach ($triangles as [$a, $b, $c]) {
            [$p1, $p2, $p3] = [$point($a), $point($b), $point($c)];
            $u = [$p2[0] - $p1[0], $p2[1] - $p1[1], $p2[2] - $p1[2]];
            $v = [$p3[0] - $p1[0], $p3[1] - $p1[1], $p3[2] - $p1[2]];
            $normal = [$u[1] * $v[2] - $u[2] * $v[1], $u[2] * $v[0] - $u[0] * $v[2], $u[0] * $v[1] - $u[1] * $v[0]];
            $length = sqrt($normal[0] ** 2 + $normal[1] ** 2 + $normal[2] ** 2) ?: 1.0;

            fwrite($handle, pack('g12v', ...[$normal[0] / $length, $normal[1] / $length, $normal[2] / $length, ...$p1, ...$p2, ...$p3, 0]));
        }

        fclose($handle);
    }

    /**
     * Dosyadaki üçgenleri sırayla verir: [x1, y1, z1, x2, y2, z2, x3, y3, z3].
     *
     * @return Generator<int, list<float>>
     */
    private function triangles(string $path): Generator
    {
        $size = filesize($path);
        $handle = fopen($path, 'rb');

        if ($size === false || $size < 84 || $handle === false) {
            throw new InvalidUploadException(__('STL dosyası okunamadı.'));
        }

        try {
            $header = fread($handle, 84);
            $declared = unpack('V', substr($header, 80, 4))[1];

            if ($size === 84 + $declared * 50) {
                yield from $this->binaryTriangles($handle, $declared);

                return;
            }

            if (str_starts_with(ltrim($header), 'solid')) {
                rewind($handle);
                yield from $this->asciiTriangles($handle);

                return;
            }

            throw new InvalidUploadException(__('STL dosyası okunamadı.'));
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, list<float>>
     */
    private function binaryTriangles($handle, int $count): Generator
    {
        $remaining = $count;

        while ($remaining > 0) {
            $batch = min(self::CHUNK_TRIANGLES, $remaining);
            $bytes = fread($handle, $batch * 50);

            if ($bytes === false || strlen($bytes) !== $batch * 50) {
                throw new InvalidUploadException(__('STL dosyası eksik veya bozuk.'));
            }

            for ($offset = 0; $offset < $batch * 50; $offset += 50) {
                /** İlk 12 bayt normaldir; köşeler 12–47 arasındadır. */
                yield array_values(unpack('g9', $bytes, $offset + 12));
            }

            $remaining -= $batch;
        }
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, list<float>>
     */
    private function asciiTriangles($handle): Generator
    {
        $vertices = [];

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\s*vertex\s+(\S+)\s+(\S+)\s+(\S+)/i', $line, $matches) !== 1) {
                continue;
            }

            array_push($vertices, (float) $matches[1], (float) $matches[2], (float) $matches[3]);

            if (count($vertices) === 9) {
                yield $vertices;
                $vertices = [];
            }
        }
    }
}
