<?php

declare(strict_types=1);

/**
 * Converts a binary signal to run-length encoding and normalizes widths.
 *
 * Run-length encoding is the fundamental representation for 1D barcode
 * decoding — it captures the sequence of bar and space widths.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

final class RunLengthEncoder
{
    /**
     * Convert binary signal to run-length encoding.
     *
     * @param list<int> $binary Binary values (1=dark, 0=light)
     * @return list<array{width: int, dark: bool}> Run sequence
     */
    public function encode(array $binary): array
    {
        if (count($binary) === 0) {
            return [];
        }

        $runs = [];
        $current = $binary[0];
        $count = 1;

        for ($i = 1, $len = count($binary); $i < $len; $i++) {
            if ($binary[$i] === $current) {
                $count++;
            } else {
                $runs[] = ['width' => $count, 'dark' => $current === 1];
                $current = $binary[$i];
                $count = 1;
            }
        }
        $runs[] = ['width' => $count, 'dark' => $current === 1];

        return $runs;
    }

    /**
     * Extract just the widths from runs (for scoring and decoding).
     *
     * @param list<array{width: int, dark: bool}> $runs
     * @return list<int>
     */
    public function widths(array $runs): array
    {
        return array_map(static fn (array $r): int => $r['width'], $runs);
    }

    /**
     * Estimate the base module width from run data.
     *
     * Uses the 10th percentile of sorted widths to avoid noise spikes.
     */
    public function estimateModuleWidth(array $widths): float
    {
        if (count($widths) === 0) {
            return 1.0;
        }

        $sorted = $widths;
        sort($sorted);

        $idx = (int) (count($sorted) * 0.1);
        return max(1.0, (float) $sorted[$idx]);
    }

    /**
     * Normalize run widths by estimated module size.
     *
     * @param list<int> $widths Raw pixel widths
     * @return list<float> Normalized widths (ideally near integers 1, 2, 3...)
     */
    public function normalize(array $widths): array
    {
        $moduleWidth = $this->estimateModuleWidth($widths);

        return array_map(
            static fn (int $w): float => $w / $moduleWidth,
            $widths,
        );
    }
}
