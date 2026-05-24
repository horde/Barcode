<?php

declare(strict_types=1);

/**
 * Converts a continuous 1D signal to binary using adaptive threshold.
 *
 * Uses a sliding window mean to handle uneven illumination across
 * the barcode stripe pattern.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

final class SignalBinarizer
{
    /**
     * Binarize a float signal using adaptive (sliding window) threshold.
     *
     * @param list<float> $signal Values between 0.0 and 1.0
     * @param int $windowSize Sliding window size for local mean
     * @return list<int> Binary values (1=dark, 0=light)
     */
    public function binarize(array $signal, int $windowSize = 15): array
    {
        $len = count($signal);
        if ($len === 0) {
            return [];
        }

        $halfWindow = (int) ($windowSize / 2);
        $binary = [];
        $windowSum = 0.0;
        $windowCount = 0;

        // Initialize window for first element
        for ($i = 0; $i < min($halfWindow + 1, $len); $i++) {
            $windowSum += $signal[$i];
            $windowCount++;
        }

        for ($i = 0; $i < $len; $i++) {
            // Add entering element
            $enterIdx = $i + $halfWindow;
            if ($enterIdx < $len && $enterIdx >= $halfWindow + 1) {
                $windowSum += $signal[$enterIdx];
                $windowCount++;
            }

            // Remove leaving element
            $leaveIdx = $i - $halfWindow - 1;
            if ($leaveIdx >= 0) {
                $windowSum -= $signal[$leaveIdx];
                $windowCount--;
            }

            $localMean = $windowCount > 0 ? $windowSum / $windowCount : 0.5;
            $binary[] = $signal[$i] >= $localMean ? 1 : 0;
        }

        return $binary;
    }

    /**
     * Simple global threshold binarization.
     *
     * @param list<float> $signal
     * @return list<int>
     */
    public function binarizeGlobal(array $signal, float $threshold = 0.5): array
    {
        return array_map(
            static fn (float $v): int => $v >= $threshold ? 1 : 0,
            $signal,
        );
    }
}
