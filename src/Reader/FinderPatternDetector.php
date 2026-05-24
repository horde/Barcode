<?php

declare(strict_types=1);

/**
 * Detects QR code finder patterns in a binarized scanline.
 *
 * Finder patterns have the characteristic 1:1:3:1:1 ratio of
 * dark:light:dark:light:dark module widths. This class scans
 * pixel rows and columns looking for this signature.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

final class FinderPatternDetector
{
    /**
     * Detected finder pattern center with estimated module size.
     *
     * @var list<array{x: float, y: float, moduleSize: float}>
     */
    private array $candidates = [];

    /**
     * Scan a binary image (row by row) for finder pattern signatures.
     *
     * @param array<int, array<int, bool>> $binaryImage Row-major, true = dark
     * @param int $width Image width
     * @param int $height Image height
     * @return list<array{x: float, y: float, moduleSize: float}> Up to 3 finder pattern centers
     */
    public function detect(array $binaryImage, int $width, int $height): array
    {
        $this->candidates = [];

        for ($row = 0; $row < $height; $row++) {
            $stateCount = [0, 0, 0, 0, 0];
            $currentState = 0;
            $started = false;

            for ($col = 0; $col < $width; $col++) {
                $dark = $binaryImage[$row][$col];
                if ($dark) {
                    if (!$started) {
                        $started = true;
                    }
                    if ($currentState === 1 || $currentState === 3) {
                        $currentState++;
                    }
                    $stateCount[$currentState]++;
                } else {
                    if (!$started) {
                        continue;
                    }
                    if ($currentState === 1 || $currentState === 3) {
                        $stateCount[$currentState]++;
                    } elseif ($currentState === 0 || $currentState === 2) {
                        $currentState++;
                        $stateCount[$currentState]++;
                    } elseif ($currentState === 4) {
                        if ($this->isFinderRatio($stateCount)) {
                            $centerCol = $this->centerFromEnd($stateCount, $col);
                            $centerRow = $this->crossCheckVertical(
                                $binaryImage,
                                $row,
                                (int) round($centerCol),
                                $stateCount[2],
                                $width,
                                $height,
                            );
                            if ($centerRow !== null) {
                                $moduleSize = array_sum($stateCount) / 7.0;
                                $this->addCandidate($centerCol, $centerRow, $moduleSize);
                            }
                        }
                        $stateCount[0] = $stateCount[2];
                        $stateCount[1] = $stateCount[3];
                        $stateCount[2] = $stateCount[4];
                        $stateCount[3] = 1;
                        $stateCount[4] = 0;
                        $currentState = 3;
                    }
                }
            }

            if ($currentState === 4 && $this->isFinderRatio($stateCount)) {
                $centerCol = $this->centerFromEnd($stateCount, $width);
                $centerRow = $this->crossCheckVertical(
                    $binaryImage,
                    $row,
                    (int) round($centerCol),
                    $stateCount[2],
                    $width,
                    $height,
                );
                if ($centerRow !== null) {
                    $moduleSize = array_sum($stateCount) / 7.0;
                    $this->addCandidate($centerCol, $centerRow, $moduleSize);
                }
            }
        }

        return $this->selectBestThree();
    }

    /**
     * Check if the state counts match the 1:1:3:1:1 finder pattern ratio.
     *
     * @param array<int, int> $stateCount
     */
    private function isFinderRatio(array $stateCount): bool
    {
        $total = array_sum($stateCount);
        if ($total < 7) {
            return false;
        }

        $moduleSize = $total / 7.0;
        $tolerance = $moduleSize * 0.5;

        return abs($stateCount[0] - $moduleSize) < $tolerance
            && abs($stateCount[1] - $moduleSize) < $tolerance
            && abs($stateCount[2] - 3.0 * $moduleSize) < 3.0 * $tolerance
            && abs($stateCount[3] - $moduleSize) < $tolerance
            && abs($stateCount[4] - $moduleSize) < $tolerance;
    }

    /**
     * @param array<int, int> $stateCount
     */
    private function centerFromEnd(array $stateCount, int $end): float
    {
        return (float) ($end - $stateCount[4] - $stateCount[3]) - $stateCount[2] / 2.0;
    }

    /**
     * Cross-check a horizontal candidate by scanning vertically.
     *
     * @param array<int, array<int, bool>> $image
     */
    private function crossCheckVertical(
        array $image,
        int $startRow,
        int $centerCol,
        int $expectedCenterWidth,
        int $width,
        int $height,
    ): ?float {
        if ($centerCol < 0 || $centerCol >= $width) {
            return null;
        }

        $stateCount = [0, 0, 0, 0, 0];

        $row = $startRow;
        while ($row >= 0 && $image[$row][$centerCol]) {
            $stateCount[2]++;
            $row--;
        }
        if ($row < 0) {
            return null;
        }
        while ($row >= 0 && !$image[$row][$centerCol]) {
            $stateCount[1]++;
            $row--;
        }
        if ($row < 0) {
            return null;
        }
        while ($row >= 0 && $image[$row][$centerCol]) {
            $stateCount[0]++;
            $row--;
        }

        $row = $startRow + 1;
        while ($row < $height && $image[$row][$centerCol]) {
            $stateCount[2]++;
            $row++;
        }
        if ($row >= $height) {
            return null;
        }
        while ($row < $height && !$image[$row][$centerCol]) {
            $stateCount[3]++;
            $row++;
        }
        if ($row >= $height) {
            return null;
        }
        while ($row < $height && $image[$row][$centerCol]) {
            $stateCount[4]++;
            $row++;
        }

        if (!$this->isFinderRatio($stateCount)) {
            return null;
        }

        $total = array_sum($stateCount);
        $expectedTotal = (int) round(7.0 * $expectedCenterWidth / 3.0);
        if (5 * abs($total - $expectedTotal) >= 2 * $expectedTotal) {
            return null;
        }

        return (float) ($row - $stateCount[4] - $stateCount[3]) - $stateCount[2] / 2.0;
    }

    private function addCandidate(float $x, float $y, float $moduleSize): void
    {
        foreach ($this->candidates as &$c) {
            if (abs($c['x'] - $x) < $moduleSize * 3 && abs($c['y'] - $y) < $moduleSize * 3) {
                $c['x'] = ($c['x'] + $x) / 2.0;
                $c['y'] = ($c['y'] + $y) / 2.0;
                $c['moduleSize'] = ($c['moduleSize'] + $moduleSize) / 2.0;
                return;
            }
        }
        $this->candidates[] = ['x' => $x, 'y' => $y, 'moduleSize' => $moduleSize];
    }

    /**
     * Select the best three finder patterns (if at least three are found).
     *
     * @return list<array{x: float, y: float, moduleSize: float}>
     */
    private function selectBestThree(): array
    {
        if (count($this->candidates) < 3) {
            return $this->candidates;
        }

        // Sort by module size consistency — pick three with most similar sizes
        usort($this->candidates, static fn (array $a, array $b): int => (int) (($a['moduleSize'] - $b['moduleSize']) * 1000));

        $bestDiff = PHP_FLOAT_MAX;
        $bestTriple = [$this->candidates[0], $this->candidates[1], $this->candidates[2]];

        $count = count($this->candidates);
        for ($i = 0; $i < $count - 2; $i++) {
            for ($j = $i + 1; $j < $count - 1; $j++) {
                for ($k = $j + 1; $k < $count; $k++) {
                    $diff = $this->candidates[$k]['moduleSize'] - $this->candidates[$i]['moduleSize'];
                    if ($diff < $bestDiff) {
                        $bestDiff = $diff;
                        $bestTriple = [$this->candidates[$i], $this->candidates[$j], $this->candidates[$k]];
                    }
                }
            }
        }

        return $bestTriple;
    }
}
