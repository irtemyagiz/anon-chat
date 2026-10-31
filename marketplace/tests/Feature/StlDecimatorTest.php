<?php

namespace Tests\Feature;

use App\Exceptions\InvalidUploadException;
use App\Services\Files\StlDecimator;
use Tests\TestCase;

class StlDecimatorTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function temporaryFile(): string
    {
        return $this->temporaryFiles[] = tempnam(sys_get_temp_dir(), 'stl');
    }

    /**
     * Enlem/boylam ızgarasıyla binary STL küre üretir: 2 * $segments * ($segments / 2) üçgen.
     */
    private function sphere(int $segments, float $radius = 50.0): string
    {
        $rings = intdiv($segments, 2);
        $point = fn (int $ring, int $segment) => [
            $radius * sin(M_PI * $ring / $rings) * cos(2 * M_PI * $segment / $segments),
            $radius * sin(M_PI * $ring / $rings) * sin(2 * M_PI * $segment / $segments),
            $radius * cos(M_PI * $ring / $rings),
        ];
        $triangles = '';
        $count = 0;

        for ($ring = 0; $ring < $rings; $ring++) {
            for ($segment = 0; $segment < $segments; $segment++) {
                $a = $point($ring, $segment);
                $b = $point($ring + 1, $segment);
                $c = $point($ring + 1, $segment + 1);
                $d = $point($ring, $segment + 1);
                $triangles .= pack('g12v', ...[0, 0, 0, ...$a, ...$b, ...$c, 0]);
                $triangles .= pack('g12v', ...[0, 0, 0, ...$a, ...$c, ...$d, 0]);
                $count += 2;
            }
        }

        $path = $this->temporaryFile();
        file_put_contents($path, str_pad('sphere', 80).pack('V', $count).$triangles);

        return $path;
    }

    /**
     * @return list<list<float>>
     */
    private function readBinary(string $path): array
    {
        $bytes = file_get_contents($path);
        $count = unpack('V', $bytes, 80)[1];
        $this->assertSame(84 + 50 * $count, strlen($bytes), 'Geçerli binary STL');

        return array_map(fn (int $i) => array_values(unpack('g12', $bytes, 84 + 50 * $i)), range(0, $count - 1));
    }

    public function test_dense_mesh_is_reduced_and_stays_within_original_bounds(): void
    {
        $source = $this->sphere(200);
        $target = $this->temporaryFile();

        $result = app(StlDecimator::class)->decimate($source, $target, grid: 40, maxTriangles: 60_000);

        $this->assertSame(40_000, $result['triangles_in']);
        $this->assertLessThan(intdiv(40_000, 3), $result['triangles_out'], 'Önizleme orijinalden belirgin şekilde kaba olmalı');
        $this->assertGreaterThan(1_000, $result['triangles_out'], 'Şekil tanınabilir kalmalı');

        foreach ($this->readBinary($target) as $triangle) {
            $normalLength = sqrt($triangle[0] ** 2 + $triangle[1] ** 2 + $triangle[2] ** 2);
            $this->assertEqualsWithDelta(1.0, $normalLength, 0.001);

            for ($i = 3; $i < 12; $i++) {
                $this->assertLessThanOrEqual(50.001, abs($triangle[$i]));
            }
        }
    }

    public function test_triangle_limit_coarsens_the_grid(): void
    {
        $target = $this->temporaryFile();

        $result = app(StlDecimator::class)->decimate($this->sphere(200), $target, grid: 80, maxTriangles: 2_000);

        $this->assertLessThanOrEqual(2_000, $result['triangles_out']);
        $this->assertLessThan(80, $result['grid']);
    }

    public function test_ascii_stl_is_supported(): void
    {
        $target = $this->temporaryFile();

        $result = app(StlDecimator::class)->decimate(base_path('tests/Fixtures/ascii.stl'), $target);

        $this->assertGreaterThan(0, $result['triangles_out']);
        $this->readBinary($target);
    }

    public function test_garbage_is_rejected(): void
    {
        $source = $this->temporaryFile();
        file_put_contents($source, str_repeat('x', 500));

        $this->expectException(InvalidUploadException::class);
        app(StlDecimator::class)->decimate($source, $this->temporaryFile());
    }
}
