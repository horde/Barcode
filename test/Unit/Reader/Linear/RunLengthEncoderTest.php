<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader\Linear;

use Horde\Barcode\Reader\Linear\RunLengthEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RunLengthEncoder::class)]
final class RunLengthEncoderTest extends TestCase
{
    public function testEncodeAlternatingBits(): void
    {
        $encoder = new RunLengthEncoder();
        $binary = [1, 1, 0, 0, 0, 1, 0, 0];

        $runs = $encoder->encode($binary);

        $this->assertSame(
            [
                ['width' => 2, 'dark' => true],
                ['width' => 3, 'dark' => false],
                ['width' => 1, 'dark' => true],
                ['width' => 2, 'dark' => false],
            ],
            $runs,
        );
    }

    public function testEncodeEmpty(): void
    {
        $encoder = new RunLengthEncoder();
        $this->assertSame([], $encoder->encode([]));
    }

    public function testEncodeSingleValue(): void
    {
        $encoder = new RunLengthEncoder();
        $runs = $encoder->encode([1, 1, 1]);

        $this->assertSame([['width' => 3, 'dark' => true]], $runs);
    }

    public function testWidthsExtraction(): void
    {
        $encoder = new RunLengthEncoder();
        $runs = [
            ['width' => 2, 'dark' => true],
            ['width' => 5, 'dark' => false],
            ['width' => 3, 'dark' => true],
        ];

        $this->assertSame([2, 5, 3], $encoder->widths($runs));
    }

    public function testEstimateModuleWidthUsesPercentile(): void
    {
        $encoder = new RunLengthEncoder();
        // 10 widths: sorted would be [1,2,2,3,3,4,5,5,6,10]
        // 10th percentile index = 1 → value 2
        $widths = [3, 5, 2, 6, 1, 3, 10, 4, 2, 5];

        $this->assertEqualsWithDelta(2.0, $encoder->estimateModuleWidth($widths), 0.001);
    }

    public function testEstimateModuleWidthEmpty(): void
    {
        $encoder = new RunLengthEncoder();
        $this->assertEqualsWithDelta(1.0, $encoder->estimateModuleWidth([]), 0.001);
    }

    public function testNormalizeProducesModuleMultiples(): void
    {
        $encoder = new RunLengthEncoder();
        // All widths are multiples of 3
        $widths = [3, 6, 9, 3, 6];

        $normalized = $encoder->normalize($widths);

        $this->assertEqualsWithDelta(1.0, $normalized[0], 0.01);
        $this->assertEqualsWithDelta(2.0, $normalized[1], 0.01);
        $this->assertEqualsWithDelta(3.0, $normalized[2], 0.01);
    }
}
