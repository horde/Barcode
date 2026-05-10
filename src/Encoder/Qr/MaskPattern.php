<?php

declare(strict_types=1);

/**
 * QR Code encoder implementing ISO/IEC 18004.
 *
 * Supports versions 1-40, all four error correction levels, and
 * automatic mode/version selection.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder\Qr;

final class MaskPattern
{
    public static function evaluate(int $mask, int $row, int $col): bool
    {
        return match ($mask) {
            0 => ($row + $col) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $col % 3 === 0,
            3 => ($row + $col) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
            5 => ($row * $col) % 2 + ($row * $col) % 3 === 0,
            6 => (($row * $col) % 2 + ($row * $col) % 3) % 2 === 0,
            7 => (($row + $col) % 2 + ($row * $col) % 3) % 2 === 0,
            default => false,
        };
    }

    /**
     * Score a matrix using the four penalty rules from ISO/IEC 18004.
     *
     * @param list<list<bool>> $matrix
     */
    public static function penalty(array $matrix): int
    {
        $size = count($matrix);
        $score = 0;

        // Rule 1: consecutive same-colored modules in row/col
        for ($i = 0; $i < $size; $i++) {
            $rowRun = 1;
            $colRun = 1;
            for ($j = 1; $j < $size; $j++) {
                if ($matrix[$i][$j] === $matrix[$i][$j - 1]) {
                    $rowRun++;
                } else {
                    if ($rowRun >= 5) {
                        $score += $rowRun - 2;
                    }
                    $rowRun = 1;
                }
                if ($matrix[$j][$i] === $matrix[$j - 1][$i]) {
                    $colRun++;
                } else {
                    if ($colRun >= 5) {
                        $score += $colRun - 2;
                    }
                    $colRun = 1;
                }
            }
            if ($rowRun >= 5) {
                $score += $rowRun - 2;
            }
            if ($colRun >= 5) {
                $score += $colRun - 2;
            }
        }

        // Rule 2: 2x2 blocks of same color
        for ($i = 0; $i < $size - 1; $i++) {
            for ($j = 0; $j < $size - 1; $j++) {
                $color = $matrix[$i][$j];
                if ($color === $matrix[$i][$j + 1]
                    && $color === $matrix[$i + 1][$j]
                    && $color === $matrix[$i + 1][$j + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3: finder-like patterns
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size - 10; $j++) {
                if ($matrix[$i][$j] && !$matrix[$i][$j + 1] && $matrix[$i][$j + 2]
                    && $matrix[$i][$j + 3] && $matrix[$i][$j + 4] && !$matrix[$i][$j + 5]
                    && $matrix[$i][$j + 6] && !$matrix[$i][$j + 7] && !$matrix[$i][$j + 8]
                    && !$matrix[$i][$j + 9] && !$matrix[$i][$j + 10]) {
                    $score += 40;
                }
                if (!$matrix[$i][$j] && !$matrix[$i][$j + 1] && !$matrix[$i][$j + 2]
                    && !$matrix[$i][$j + 3] && $matrix[$i][$j + 4] && !$matrix[$i][$j + 5]
                    && $matrix[$i][$j + 6] && $matrix[$i][$j + 7] && $matrix[$i][$j + 8]
                    && !$matrix[$i][$j + 9] && $matrix[$i][$j + 10]) {
                    $score += 40;
                }
                if ($matrix[$j][$i] && !$matrix[$j + 1][$i] && $matrix[$j + 2][$i]
                    && $matrix[$j + 3][$i] && $matrix[$j + 4][$i] && !$matrix[$j + 5][$i]
                    && $matrix[$j + 6][$i] && !$matrix[$j + 7][$i] && !$matrix[$j + 8][$i]
                    && !$matrix[$j + 9][$i] && !$matrix[$j + 10][$i]) {
                    $score += 40;
                }
                if (!$matrix[$j][$i] && !$matrix[$j + 1][$i] && !$matrix[$j + 2][$i]
                    && !$matrix[$j + 3][$i] && $matrix[$j + 4][$i] && !$matrix[$j + 5][$i]
                    && $matrix[$j + 6][$i] && $matrix[$j + 7][$i] && $matrix[$j + 8][$i]
                    && !$matrix[$j + 9][$i] && $matrix[$j + 10][$i]) {
                    $score += 40;
                }
            }
        }

        // Rule 4: proportion of dark modules
        $dark = 0;
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j < $size; $j++) {
                if ($matrix[$i][$j]) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $percent = (int) (($dark * 100) / $total);
        $prev5 = abs($percent - $percent % 5 - 50) / 5;
        $next5 = abs($percent - $percent % 5 + 5 - 50) / 5;
        $score += (int) min($prev5, $next5) * 10;

        return $score;
    }
}
