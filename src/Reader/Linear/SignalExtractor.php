<?php

declare(strict_types=1);

/**
 * Collapses a 2D binary image region to a 1D luminance signal.
 *
 * Averages pixel values vertically to produce a single scanline
 * representing the barcode stripe pattern.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

final class SignalExtractor
{
    /**
     * Collapse a binary image region to a 1D signal by vertical averaging.
     *
     * @param array<int, array<int, bool>> $pixels Row-major binary image (true=dark)
     * @param int $yStart Start row (inclusive)
     * @param int $yEnd End row (exclusive)
     * @return list<float> Signal values per column (0.0=white, 1.0=black)
     */
    public function extract(array $pixels, int $yStart, int $yEnd): array
    {
        if ($yStart >= $yEnd || !isset($pixels[$yStart])) {
            return [];
        }

        $width = count($pixels[$yStart]);
        $rowCount = $yEnd - $yStart;
        $signal = [];

        for ($x = 0; $x < $width; $x++) {
            $sum = 0;
            for ($y = $yStart; $y < $yEnd; $y++) {
                if (isset($pixels[$y][$x]) && $pixels[$y][$x]) {
                    $sum++;
                }
            }
            $signal[] = $sum / $rowCount;
        }

        return $signal;
    }

    /**
     * Multi-scanline extraction: average multiple horizontal bands.
     *
     * @param array<int, array<int, bool>> $pixels
     * @return list<float>
     */
    public function extractMultiLine(array $pixels, int $height, int $lines = 3): array
    {
        if ($height < 1 || $lines < 1) {
            return [];
        }

        $width = isset($pixels[0]) ? count($pixels[0]) : 0;
        if ($width === 0) {
            return [];
        }

        $bandHeight = max(1, (int) ($height * 0.05));
        $combined = array_fill(0, $width, 0.0);

        for ($i = 0; $i < $lines; $i++) {
            $center = (int) ($height * (0.4 + 0.2 * $i / max(1, $lines - 1)));
            $yStart = max(0, $center - (int) ($bandHeight / 2));
            $yEnd = min($height, $yStart + $bandHeight);

            $signal = $this->extract($pixels, $yStart, $yEnd);
            for ($x = 0; $x < $width; $x++) {
                $combined[$x] += $signal[$x] ?? 0.0;
            }
        }

        return array_map(static fn (float $v): float => $v / $lines, $combined);
    }
}
