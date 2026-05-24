<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader\Linear;

use Horde\Barcode\Reader\Linear\BarcodeScorer;
use Horde\Barcode\Reader\Linear\RunLengthEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BarcodeScorer::class)]
final class BarcodeScorerTest extends TestCase
{
    public function testScoreBarcodeLikePattern(): void
    {
        $scorer = new BarcodeScorer();
        // Simulate a barcode-like pattern: regular widths that are integer multiples
        $widths = [2, 1, 2, 2, 2, 2, 2, 2, 2, 1, 2, 2, 1, 1, 2, 2, 3, 2, 1, 1, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2];

        $score = $scorer->score($widths);

        $this->assertGreaterThan(3.0, $score);
        $this->assertTrue($scorer->isLikelyBarcode($widths));
    }

    public function testBarcodePatternsScoreHigherThanNoise(): void
    {
        $scorer = new BarcodeScorer();

        // Barcode-like: integer multiple widths, few distinct bins
        $barcode = [2, 1, 2, 2, 2, 2, 2, 2, 2, 1, 2, 2, 1, 1, 2, 2, 3, 2, 1, 1, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2];

        // Noise: many distinct values, non-integer relationships
        $noise = [1, 7, 2, 15, 1, 22, 3, 8, 1, 19, 2, 11, 1, 25, 4, 3];

        $this->assertGreaterThan($scorer->score($noise), $scorer->score($barcode));
    }

    public function testScoreTooFewRuns(): void
    {
        $scorer = new BarcodeScorer();
        $this->assertEqualsWithDelta(0.0, $scorer->score([1, 2, 3]), 0.001);
    }

    public function testRealBarcodeWidthsScoreHigh(): void
    {
        // Generate actual EAN-13 widths from encoder
        $encoder = new \Horde\Barcode\Encoder\EanUpcEncoder();
        $pattern = $encoder->encode('5901234123457');
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * 3);
        }

        $scorer = new BarcodeScorer();
        $this->assertTrue($scorer->isLikelyBarcode($widths));
    }
}
