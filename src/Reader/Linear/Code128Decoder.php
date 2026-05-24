<?php

declare(strict_types=1);

/**
 * Decodes Code 128 barcodes from run-length data.
 *
 * Code 128 structure: start(6) + data(6×N) + check(6) + stop(7)
 * Each symbol is 6 elements (bar-space alternating) summing to 11 modules.
 * Stop symbol has 7 elements summing to 13 modules.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

use Horde\Barcode\Reader\SymbolType;

final class Code128Decoder implements SymbologyDecoderInterface
{
    private const START_A = 103;
    private const START_B = 104;
    private const START_C = 105;
    private const CODE_A = 101;
    private const CODE_B = 100;
    private const CODE_C = 99;
    private const STOP = 106;

    private const PATTERNS = [
        [2,1,2,2,2,2], [2,2,2,1,2,2], [2,2,2,2,2,1], [1,2,1,2,2,3], [1,2,1,3,2,2],
        [1,3,1,2,2,2], [1,2,2,2,1,3], [1,2,2,3,1,2], [1,3,2,2,1,2], [2,2,1,2,1,3],
        [2,2,1,3,1,2], [2,3,1,2,1,2], [1,1,2,2,3,2], [1,2,2,1,3,2], [1,2,2,2,3,1],
        [1,1,3,2,2,2], [1,2,3,1,2,2], [1,2,3,2,2,1], [2,2,3,2,1,1], [2,2,1,1,3,2],
        [2,2,1,2,3,1], [2,1,3,2,1,2], [2,2,3,1,1,2], [3,1,2,1,3,1], [3,1,1,2,2,2],
        [3,2,1,1,2,2], [3,2,1,2,2,1], [3,1,2,2,1,2], [3,2,2,1,1,2], [3,2,2,2,1,1],
        [2,1,2,1,2,3], [2,1,2,3,2,1], [2,3,2,1,2,1], [1,1,1,3,2,3], [1,3,1,1,2,3],
        [1,3,1,3,2,1], [1,1,2,3,1,3], [1,3,2,1,1,3], [1,3,2,3,1,1], [2,1,1,3,1,3],
        [2,3,1,1,1,3], [2,3,1,3,1,1], [1,1,2,1,3,3], [1,1,2,3,3,1], [1,3,2,1,3,1],
        [1,1,3,1,2,3], [1,1,3,3,2,1], [1,3,3,1,2,1], [3,1,3,1,2,1], [2,1,1,3,3,1],
        [2,3,1,1,3,1], [2,1,3,1,1,3], [2,1,3,3,1,1], [2,1,3,1,3,1], [3,1,1,1,2,3],
        [3,1,1,3,2,1], [3,3,1,1,2,1], [3,1,2,1,1,3], [3,1,2,3,1,1], [3,3,2,1,1,1],
        [3,1,4,1,1,1], [2,2,1,4,1,1], [4,3,1,1,1,1], [1,1,1,2,2,4], [1,1,1,4,2,2],
        [1,2,1,1,2,4], [1,2,1,4,2,1], [1,4,1,1,2,2], [1,4,1,2,2,1], [1,1,2,2,1,4],
        [1,1,2,4,1,2], [1,2,2,1,1,4], [1,2,2,4,1,1], [1,4,2,1,1,2], [1,4,2,2,1,1],
        [2,4,1,2,1,1], [2,2,1,1,1,4], [4,1,3,1,1,1], [2,4,1,1,1,2], [1,3,4,1,1,1],
        [1,1,1,2,4,2], [1,2,1,1,4,2], [1,2,1,2,4,1], [1,1,4,2,1,2], [1,2,4,1,1,2],
        [1,2,4,2,1,1], [4,1,1,2,1,2], [4,2,1,1,1,2], [4,2,1,2,1,1], [2,1,2,1,4,1],
        [2,1,4,1,2,1], [4,1,2,1,2,1], [1,1,1,1,4,3], [1,1,1,3,4,1], [1,3,1,1,4,1],
        [1,1,4,1,1,3], [1,1,4,3,1,1], [4,1,1,1,1,3], [4,1,1,3,1,1], [1,1,3,1,4,1],
        [1,1,4,1,3,1], [3,1,1,1,4,1], [4,1,1,1,3,1], [2,1,1,4,1,2], [2,1,1,2,1,4],
        [2,1,1,2,3,2], [2,3,3,1,1,1,2],
    ];

    public function tryDecode(array $widths): ?DecodedBarcode
    {
        $start = $this->findStartPattern($widths);
        if ($start === null) {
            return null;
        }

        [$pos, $startSymbol] = $start;
        $moduleWidth = $this->estimateModule($widths, $pos);

        $symbols = [$startSymbol];
        $pos += 6;
        $len = count($widths);

        // Decode data symbols: each is 6 elements. Stop is 7 elements at the end.
        // Only check for stop when remaining elements can't fit another data symbol + stop (< 13).
        while ($pos + 6 <= $len) {
            $remaining = $len - $pos;

            if ($remaining < 13 && $remaining >= 7 && $this->isStopPattern($widths, $pos, $moduleWidth)) {
                break;
            }

            if ($remaining < 6) {
                break;
            }

            $symbol = $this->decodeSymbol($widths, $pos, $moduleWidth);
            if ($symbol === null) {
                return null;
            }
            $symbols[] = $symbol;
            $pos += 6;
        }

        if (count($symbols) < 3) {
            return null;
        }

        // Last data symbol is the check digit
        $checkSymbol = array_pop($symbols);

        // Validate checksum
        if (!$this->validateChecksum($symbols, $checkSymbol)) {
            return null;
        }

        // Decode symbols to string
        $payload = $this->symbolsToString($symbols);
        if ($payload === null) {
            return null;
        }

        return new DecodedBarcode($payload, SymbolType::Code128);
    }

    /**
     * @return array{int, int}|null [position, start symbol value]
     */
    private function findStartPattern(array $widths): ?array
    {
        $len = count($widths);

        for ($i = 0; $i < $len - 12; $i++) {
            $slice = array_slice($widths, $i, 6);
            $total = array_sum($slice);
            if ($total < 6) {
                continue;
            }

            $symbol = $this->matchPattern($slice, $total);
            if ($symbol === self::START_A || $symbol === self::START_B || $symbol === self::START_C) {
                return [$i, $symbol];
            }
        }

        return null;
    }

    private function estimateModule(array $widths, int $pos): float
    {
        $total = 0;
        for ($i = 0; $i < 6; $i++) {
            $total += $widths[$pos + $i];
        }
        return $total / 11.0;
    }

    private function decodeSymbol(array $widths, int $pos, float $moduleWidth): ?int
    {
        if ($pos + 5 >= count($widths)) {
            return null;
        }

        $slice = array_slice($widths, $pos, 6);
        $total = array_sum($slice);

        $symbol = $this->matchPattern($slice, $total);
        if ($symbol === null || $symbol >= self::START_A) {
            return null;
        }

        return $symbol;
    }

    private function matchPattern(array $slice, int $total): ?int
    {
        $bestSymbol = null;
        $bestError = PHP_FLOAT_MAX;

        for ($s = 0; $s < 107; $s++) {
            $pattern = self::PATTERNS[$s];
            $patternSum = array_sum($pattern);
            $scale = $total / $patternSum;

            $error = 0.0;
            for ($i = 0; $i < 6; $i++) {
                $expected = $pattern[$i] * $scale;
                $error += abs($slice[$i] - $expected);
            }

            if ($error < $bestError) {
                $bestError = $error;
                $bestSymbol = $s;
            }
        }

        $tolerance = $total * 0.35;
        if ($bestError > $tolerance) {
            return null;
        }

        return $bestSymbol;
    }

    private function isStopPattern(array $widths, int $pos, float $moduleWidth): bool
    {
        if ($pos + 6 >= count($widths)) {
            return false;
        }

        $slice = array_slice($widths, $pos, 7);
        if (count($slice) < 7) {
            return false;
        }

        $total = array_sum($slice);
        $expected = self::PATTERNS[self::STOP];
        $patternSum = array_sum($expected);
        $scale = $total / $patternSum;

        $error = 0.0;
        for ($i = 0; $i < 7; $i++) {
            $exp = $expected[$i] * $scale;
            $error += abs($slice[$i] - $exp);
        }

        return $error < $total * 0.35;
    }

    private function validateChecksum(array $symbols, int $checkSymbol): bool
    {
        $sum = $symbols[0]; // Start code value
        for ($i = 1; $i < count($symbols); $i++) {
            $sum += $symbols[$i] * $i;
        }
        return ($sum % 103) === $checkSymbol;
    }

    /**
     * @param list<int> $symbols Including start code at [0]
     */
    private function symbolsToString(array $symbols): ?string
    {
        if (count($symbols) < 1) {
            return null;
        }

        $startCode = $symbols[0];
        $currentSet = match ($startCode) {
            self::START_A => 'A',
            self::START_B => 'B',
            self::START_C => 'C',
            default => null,
        };

        if ($currentSet === null) {
            return null;
        }

        $result = '';
        for ($i = 1; $i < count($symbols); $i++) {
            $value = $symbols[$i];

            // Handle set switching
            if ($value === self::CODE_A) {
                $currentSet = 'A';
                continue;
            }
            if ($value === self::CODE_B) {
                $currentSet = 'B';
                continue;
            }
            if ($value === self::CODE_C) {
                $currentSet = 'C';
                continue;
            }

            $result .= match ($currentSet) {
                'A' => $this->decodeSetA($value),
                'B' => $this->decodeSetB($value),
                'C' => $this->decodeSetC($value),
            };
        }

        return $result;
    }

    private function decodeSetA(int $value): string
    {
        if ($value < 64) {
            return chr($value + 32);
        }
        if ($value < 96) {
            return chr($value - 64);
        }
        return '';
    }

    private function decodeSetB(int $value): string
    {
        if ($value < 96) {
            return chr($value + 32);
        }
        return '';
    }

    private function decodeSetC(int $value): string
    {
        if ($value >= 0 && $value <= 99) {
            return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
        }
        return '';
    }
}
