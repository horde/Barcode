<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Reader\QrGridExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrGridExtractor::class)]
final class QrGridExtractorTest extends TestCase
{
    public function testExtractPerfectGrid(): void
    {
        $encoder = new QrEncoder();
        $matrix = $encoder->encode('HELLO', ErrorCorrectionLevel::M);
        $size = $matrix->width();

        [$pixels, $finders, $width, $height] = $this->renderMatrixToBinary($matrix, 10, 4);

        $extractor = new QrGridExtractor();
        $extracted = $extractor->extract($pixels, $finders, $width, $height);

        $this->assertNotNull($extracted);
        $this->assertSame($size, $extracted->width());
        $this->assertSame($size, $extracted->height());

        $mismatches = 0;
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix->isDark($row, $col) !== $extracted->isDark($row, $col)) {
                    $mismatches++;
                }
            }
        }
        $this->assertSame(0, $mismatches, "Extracted matrix has {$mismatches} mismatched modules");
    }

    public function testExtractReturnsNullWithFewerThanThreeFinders(): void
    {
        $pixels = array_fill(0, 100, array_fill(0, 100, false));
        $finders = [
            ['x' => 30.0, 'y' => 30.0, 'moduleSize' => 5.0],
            ['x' => 70.0, 'y' => 30.0, 'moduleSize' => 5.0],
        ];

        $extractor = new QrGridExtractor();
        $result = $extractor->extract($pixels, $finders, 100, 100);

        $this->assertNull($result);
    }

    public function testFinderOrderDoesNotAffectResult(): void
    {
        $encoder = new QrEncoder();
        $matrix = $encoder->encode('TEST', ErrorCorrectionLevel::L);

        [$pixels, $finders, $width, $height] = $this->renderMatrixToBinary($matrix, 8, 4);

        $extractor = new QrGridExtractor();

        // Original order
        $result1 = $extractor->extract($pixels, $finders, $width, $height);
        // Reversed order
        $result2 = $extractor->extract($pixels, array_reverse($finders), $width, $height);
        // Rotated order
        $result3 = $extractor->extract($pixels, [$finders[1], $finders[2], $finders[0]], $width, $height);

        $this->assertNotNull($result1);
        $this->assertNotNull($result2);
        $this->assertNotNull($result3);

        $size = $matrix->width();
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                $this->assertSame(
                    $result1->isDark($row, $col),
                    $result2->isDark($row, $col),
                    "Mismatch at ({$row},{$col}) with reversed finders",
                );
                $this->assertSame(
                    $result1->isDark($row, $col),
                    $result3->isDark($row, $col),
                    "Mismatch at ({$row},{$col}) with rotated finders",
                );
            }
        }
    }

    /**
     * Render a ModuleMatrix into a binary pixel array and return finder centers.
     *
     * @return array{array<int, array<int, bool>>, list<array{x: float, y: float, moduleSize: float}>, int, int}
     */
    private function renderMatrixToBinary(ModuleMatrix $matrix, int $scale, int $quiet): array
    {
        $size = $matrix->width();
        $imgSize = ($size + 2 * $quiet) * $scale;
        $pixels = array_fill(0, $imgSize, array_fill(0, $imgSize, false));

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix->isDark($row, $col)) {
                    $px = ($col + $quiet) * $scale;
                    $py = ($row + $quiet) * $scale;
                    for ($dy = 0; $dy < $scale; $dy++) {
                        for ($dx = 0; $dx < $scale; $dx++) {
                            $pixels[$py + $dy][$px + $dx] = true;
                        }
                    }
                }
            }
        }

        $finders = [
            ['x' => ($quiet + 3.5) * $scale, 'y' => ($quiet + 3.5) * $scale, 'moduleSize' => (float) $scale],
            ['x' => ($quiet + $size - 3.5) * $scale, 'y' => ($quiet + 3.5) * $scale, 'moduleSize' => (float) $scale],
            ['x' => ($quiet + 3.5) * $scale, 'y' => ($quiet + $size - 3.5) * $scale, 'moduleSize' => (float) $scale],
        ];

        return [$pixels, $finders, $imgSize, $imgSize];
    }
}
