<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader\Linear;

use Horde\Barcode\Reader\Linear\SignalExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignalExtractor::class)]
final class SignalExtractorTest extends TestCase
{
    public function testExtractAllDark(): void
    {
        $pixels = [
            [true, true, true, true],
            [true, true, true, true],
            [true, true, true, true],
        ];

        $extractor = new SignalExtractor();
        $signal = $extractor->extract($pixels, 0, 3);

        $this->assertCount(4, $signal);
        foreach ($signal as $value) {
            $this->assertEqualsWithDelta(1.0, $value, 0.001);
        }
    }

    public function testExtractAllWhite(): void
    {
        $pixels = [
            [false, false, false],
            [false, false, false],
        ];

        $extractor = new SignalExtractor();
        $signal = $extractor->extract($pixels, 0, 2);

        $this->assertCount(3, $signal);
        foreach ($signal as $value) {
            $this->assertEqualsWithDelta(0.0, $value, 0.001);
        }
    }

    public function testExtractMixedProducesAverage(): void
    {
        $pixels = [
            [true, false, true],
            [false, false, true],
        ];

        $extractor = new SignalExtractor();
        $signal = $extractor->extract($pixels, 0, 2);

        $this->assertEqualsWithDelta(0.5, $signal[0], 0.001);
        $this->assertEqualsWithDelta(0.0, $signal[1], 0.001);
        $this->assertEqualsWithDelta(1.0, $signal[2], 0.001);
    }

    public function testExtractSubsetOfRows(): void
    {
        $pixels = [
            [true, true, true],
            [false, false, false],
            [true, true, true],
            [false, false, false],
        ];

        $extractor = new SignalExtractor();
        $signal = $extractor->extract($pixels, 1, 3);

        $this->assertEqualsWithDelta(0.5, $signal[0], 0.001);
    }

    public function testExtractEmptyOnInvalidRange(): void
    {
        $pixels = [[true, false]];
        $extractor = new SignalExtractor();

        $this->assertSame([], $extractor->extract($pixels, 5, 10));
        $this->assertSame([], $extractor->extract($pixels, 2, 1));
    }

    public function testExtractMultiLineProducesSignal(): void
    {
        $height = 20;
        $width = 30;
        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $row[] = ($x % 4 < 2);
            }
            $pixels[] = $row;
        }

        $extractor = new SignalExtractor();
        $signal = $extractor->extractMultiLine($pixels, $height, 3);

        $this->assertCount($width, $signal);
        $this->assertGreaterThan(0.5, $signal[0]);
        $this->assertLessThan(0.5, $signal[2]);
    }
}
