<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Reader\FinderPatternDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FinderPatternDetector::class)]
final class FinderPatternDetectorTest extends TestCase
{
    public function testDetectsThreeFindersInSyntheticImage(): void
    {
        [$pixels, $width, $height] = $this->buildQrFinderImage(21, 10, 4);

        $detector = new FinderPatternDetector();
        $finders = $detector->detect($pixels, $width, $height);

        $this->assertCount(3, $finders);
    }

    public function testNoFindersInBlankImage(): void
    {
        $width = 100;
        $height = 100;
        $pixels = array_fill(0, $height, array_fill(0, $width, false));

        $detector = new FinderPatternDetector();
        $finders = $detector->detect($pixels, $width, $height);

        $this->assertLessThan(3, count($finders));
    }

    public function testNoFindersInSolidBlackImage(): void
    {
        $width = 100;
        $height = 100;
        $pixels = array_fill(0, $height, array_fill(0, $width, true));

        $detector = new FinderPatternDetector();
        $finders = $detector->detect($pixels, $width, $height);

        $this->assertLessThan(3, count($finders));
    }

    public function testFinderCentersAccuracy(): void
    {
        $scale = 10;
        $quiet = 4;
        $size = 21;
        [$pixels, $width, $height] = $this->buildQrFinderImage($size, $scale, $quiet);

        $detector = new FinderPatternDetector();
        $finders = $detector->detect($pixels, $width, $height);

        $this->assertCount(3, $finders);

        $expectedCenters = [
            [($quiet + 3.5) * $scale, ($quiet + 3.5) * $scale],
            [($quiet + $size - 3.5) * $scale, ($quiet + 3.5) * $scale],
            [($quiet + 3.5) * $scale, ($quiet + $size - 3.5) * $scale],
        ];

        foreach ($expectedCenters as $expected) {
            $found = false;
            foreach ($finders as $f) {
                if (abs($f['x'] - $expected[0]) < $scale && abs($f['y'] - $expected[1]) < $scale) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, sprintf(
                'Expected finder near (%.0f, %.0f) not found',
                $expected[0],
                $expected[1],
            ));
        }
    }

    public function testSingleFinderNotSufficient(): void
    {
        $scale = 10;
        $quiet = 4;
        $imgSize = (7 + 2 * $quiet) * $scale;
        $pixels = array_fill(0, $imgSize, array_fill(0, $imgSize, false));

        $this->drawFinder($pixels, ($quiet + 3) * $scale, ($quiet + 3) * $scale, $scale);

        $detector = new FinderPatternDetector();
        $finders = $detector->detect($pixels, $imgSize, $imgSize);

        $this->assertLessThan(3, count($finders));
    }

    /**
     * Build a binary image containing three QR finder patterns at the
     * standard positions for a given QR version size.
     *
     * @return array{array<int, array<int, bool>>, int, int}
     */
    private function buildQrFinderImage(int $qrSize, int $scale, int $quiet): array
    {
        $imgSize = ($qrSize + 2 * $quiet) * $scale;
        $pixels = array_fill(0, $imgSize, array_fill(0, $imgSize, false));

        // Top-left finder at module (0,0)
        $this->drawFinder($pixels, ($quiet + 3) * $scale, ($quiet + 3) * $scale, $scale);
        // Top-right finder at module (size-7, 0)
        $this->drawFinder($pixels, ($quiet + $qrSize - 4) * $scale, ($quiet + 3) * $scale, $scale);
        // Bottom-left finder at module (0, size-7)
        $this->drawFinder($pixels, ($quiet + 3) * $scale, ($quiet + $qrSize - 4) * $scale, $scale);

        return [$pixels, $imgSize, $imgSize];
    }

    /**
     * Draw a 7x7 finder pattern centered at ($cx, $cy) with given module scale.
     *
     * @param array<int, array<int, bool>> &$pixels
     */
    private function drawFinder(array &$pixels, int $cx, int $cy, int $scale): void
    {
        // Finder pattern: 7x7 with 1:1:3:1:1 ratio
        // DDD DDDD
        // D     D
        // D DDD D
        // D DDD D
        // D DDD D
        // D     D
        // DDDDDDD
        $pattern = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1],
        ];

        $startX = $cx - 3 * $scale;
        $startY = $cy - 3 * $scale;

        for ($row = 0; $row < 7; $row++) {
            for ($col = 0; $col < 7; $col++) {
                if ($pattern[$row][$col] === 1) {
                    $px = $startX + $col * $scale;
                    $py = $startY + $row * $scale;
                    for ($dy = 0; $dy < $scale; $dy++) {
                        for ($dx = 0; $dx < $scale; $dx++) {
                            if (isset($pixels[$py + $dy][$px + $dx])) {
                                $pixels[$py + $dy][$px + $dx] = true;
                            }
                        }
                    }
                }
            }
        }
    }
}
