<?php

declare(strict_types=1);

/**
 * Scores a run-length sequence for "barcode-likeness".
 *
 * Combines multiple heuristics to determine whether a given
 * stripe pattern is likely a valid 1D barcode vs noise/texture.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

final class BarcodeScorer
{
    /**
     * Score a run-length sequence for barcode-likeness.
     *
     * @param list<int> $widths Raw pixel widths of runs
     * @return float Score (higher = more barcode-like, typically 0-7)
     */
    public function score(array $widths): float
    {
        if (count($widths) < 10) {
            return 0.0;
        }

        $encoder = new RunLengthEncoder();
        $moduleWidth = $encoder->estimateModuleWidth($widths);
        $normalized = $encoder->normalize($widths);

        return 2.5 * $this->quantizationScore($normalized)
             + 1.5 * $this->binCountScore($normalized)
             + 1.5 * $this->entropyScore($widths, $moduleWidth)
             + 1.0 * $this->shortRunPenalty($widths, $moduleWidth)
             + 0.5 * $this->transitionDensityScore($widths);
    }

    /**
     * Whether the score exceeds the barcode-likeness threshold.
     */
    public function isLikelyBarcode(array $widths, float $threshold = 3.0): bool
    {
        return $this->score($widths) >= $threshold;
    }

    /**
     * Quantization error: how close normalized widths are to integers.
     */
    private function quantizationScore(array $normalized): float
    {
        $error = 0.0;
        foreach ($normalized as $r) {
            $nearest = round($r);
            $error += abs($r - $nearest);
        }
        $error /= count($normalized);

        return 1.0 / (1.0 + $error);
    }

    /**
     * Width bin count: barcodes have few distinct width multiples (2-4).
     */
    private function binCountScore(array $normalized): float
    {
        $bins = [];
        foreach ($normalized as $r) {
            $b = (int) round($r);
            $bins[$b] = true;
        }

        return 1.0 / (1.0 + max(0, count($bins) - 4));
    }

    /**
     * Entropy of width distribution: low entropy = structured.
     */
    private function entropyScore(array $widths, float $moduleWidth): float
    {
        $bins = [];
        foreach ($widths as $r) {
            $k = max(1, (int) round($r / $moduleWidth));
            $bins[$k] = ($bins[$k] ?? 0) + 1;
        }

        $total = array_sum($bins);
        $entropy = 0.0;
        foreach ($bins as $count) {
            $p = $count / $total;
            if ($p > 0.05) {
                $entropy -= $p * log($p);
            }
        }

        $n = count($bins);
        if ($n <= 1) {
            return 1.0;
        }

        $entropyNorm = $entropy / log($n);
        return 1.0 - $entropyNorm;
    }

    /**
     * Penalize very short runs (likely noise).
     */
    private function shortRunPenalty(array $widths, float $moduleWidth): float
    {
        $small = 0;
        foreach ($widths as $r) {
            if ($r < 0.5 * $moduleWidth) {
                $small++;
            }
        }

        return 1.0 - ($small / count($widths));
    }

    /**
     * Transition density: barcodes have moderate transition rate.
     */
    private function transitionDensityScore(array $widths): float
    {
        $totalWidth = array_sum($widths);
        if ($totalWidth === 0) {
            return 0.0;
        }

        $transitions = count($widths) - 1;
        $density = $transitions / $totalWidth;

        // Good barcode: density in range 0.05-0.3
        if ($density >= 0.05 && $density <= 0.3) {
            return 1.0;
        }
        if ($density < 0.05) {
            return $density / 0.05;
        }
        return max(0.0, 1.0 - ($density - 0.3) / 0.3);
    }
}
