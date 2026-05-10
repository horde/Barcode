<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit;

use Horde\Barcode\Encoder\DataMatrixEncoder;
use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Exception\CapacityExceededException;
use Horde\Barcode\Exception\EncodingException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataMatrixEncoder::class)]
class DataMatrixEncoderTest extends TestCase
{
    public function testEncodeShortString(): void
    {
        $encoder = new DataMatrixEncoder();
        $matrix = $encoder->encode('Hello');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
        $this->assertGreaterThanOrEqual(10, $matrix->width());
        $this->assertGreaterThanOrEqual(10, $matrix->height());
    }

    public function testEncodeNumericString(): void
    {
        $encoder = new DataMatrixEncoder();
        $matrix = $encoder->encode('1234567890');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
        $this->assertGreaterThanOrEqual(10, $matrix->width());
    }

    public function testEncodeUrl(): void
    {
        $encoder = new DataMatrixEncoder();
        $matrix = $encoder->encode('https://horde.org');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
    }

    public function testEmptyStringThrows(): void
    {
        $encoder = new DataMatrixEncoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('');
    }

    public function testCapacityExceeded(): void
    {
        $encoder = new DataMatrixEncoder();
        $this->expectException(CapacityExceededException::class);
        $encoder->encode(str_repeat('X', 3000));
    }

    public function testFinderPattern(): void
    {
        $encoder = new DataMatrixEncoder();
        $matrix = $encoder->encode('Test');

        $h = $matrix->height();
        $w = $matrix->width();

        // Left column is always solid dark (L-pattern)
        for ($row = 0; $row < $h; $row++) {
            $this->assertTrue($matrix->isDark($row, 0), "Left column row $row should be dark (L-pattern)");
        }

        // Bottom row is always solid dark (L-pattern)
        for ($col = 0; $col < $w; $col++) {
            $this->assertTrue($matrix->isDark($h - 1, $col), "Bottom row col $col should be dark (L-pattern)");
        }

        // Top row alternates (clock track): dark, light, dark, light...
        for ($col = 0; $col < $w; $col++) {
            $expected = ($col % 2 === 0);
            $this->assertSame($expected, $matrix->isDark(0, $col), "Top row col $col should alternate");
        }
    }

    public function testMatrixDimensionsAreEven(): void
    {
        $encoder = new DataMatrixEncoder();
        $matrix = $encoder->encode('Even check');

        $this->assertSame(0, $matrix->width() % 2);
        $this->assertSame(0, $matrix->height() % 2);
    }
}
