<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader\Linear;

use Horde\Barcode\Reader\Linear\SignalBinarizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignalBinarizer::class)]
final class SignalBinarizerTest extends TestCase
{
    public function testBinarizeGlobalAboveThreshold(): void
    {
        $binarizer = new SignalBinarizer();
        $signal = [0.8, 0.9, 0.7, 0.2, 0.1, 0.3];

        $binary = $binarizer->binarizeGlobal($signal, 0.5);

        $this->assertSame([1, 1, 1, 0, 0, 0], $binary);
    }

    public function testBinarizeGlobalCustomThreshold(): void
    {
        $binarizer = new SignalBinarizer();
        $signal = [0.3, 0.5, 0.7, 0.9];

        $binary = $binarizer->binarizeGlobal($signal, 0.6);

        $this->assertSame([0, 0, 1, 1], $binary);
    }

    public function testBinarizeAdaptiveHandlesUniformSignal(): void
    {
        $binarizer = new SignalBinarizer();
        $signal = [0.5, 0.5, 0.5, 0.5, 0.5];

        $binary = $binarizer->binarize($signal, 3);

        $this->assertCount(5, $binary);
    }

    public function testBinarizeAdaptiveDetectsTransitions(): void
    {
        $binarizer = new SignalBinarizer();
        // Simulate a bar-space-bar pattern with gradual illumination change
        $signal = [];
        for ($i = 0; $i < 30; $i++) {
            $base = 0.1 + ($i / 30.0) * 0.2; // slow gradient
            if ($i >= 5 && $i < 12) {
                $signal[] = $base + 0.5; // dark bar
            } elseif ($i >= 15 && $i < 22) {
                $signal[] = $base + 0.5; // another dark bar
            } else {
                $signal[] = $base; // light space
            }
        }

        $binary = $binarizer->binarize($signal, 7);

        $this->assertCount(30, $binary);
        // Center of first bar should be dark
        $this->assertSame(1, $binary[8]);
        // Space between bars should be light
        $this->assertSame(0, $binary[13]);
    }

    public function testBinarizeEmptySignal(): void
    {
        $binarizer = new SignalBinarizer();
        $this->assertSame([], $binarizer->binarize([]));
        $this->assertSame([], $binarizer->binarizeGlobal([]));
    }
}
