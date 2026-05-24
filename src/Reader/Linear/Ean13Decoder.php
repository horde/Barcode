<?php

declare(strict_types=1);

/**
 * Decodes EAN-13 barcodes from run-length data.
 *
 * EAN-13 structure: start(3) + left(24) + center(5) + right(24) + end(3) = 59 runs
 * Each digit is encoded as 4 runs (2 bars + 2 spaces) summing to 7 modules.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

use Horde\Barcode\Reader\SymbolType;

final class Ean13Decoder implements SymbologyDecoderInterface
{
    private const L_PATTERNS = [
        [3,2,1,1], [2,2,2,1], [2,1,2,2], [1,4,1,1], [1,1,3,2],
        [1,2,3,1], [1,1,1,4], [1,3,1,2], [1,2,1,3], [3,1,1,2],
    ];

    private const G_PATTERNS = [
        [1,1,2,3], [1,2,2,2], [2,2,1,2], [1,1,4,1], [2,3,1,1],
        [1,3,2,1], [4,1,1,1], [2,1,3,1], [3,1,2,1], [2,1,1,3],
    ];

    private const R_PATTERNS = [
        [3,2,1,1], [2,2,2,1], [2,1,2,2], [1,4,1,1], [1,1,3,2],
        [1,2,3,1], [1,1,1,4], [1,3,1,2], [1,2,1,3], [3,1,1,2],
    ];

    private const FIRST_DIGIT_ENCODING = [
        ['L','L','L','L','L','L'],
        ['L','L','G','L','G','G'],
        ['L','L','G','G','L','G'],
        ['L','L','G','G','G','L'],
        ['L','G','L','L','G','G'],
        ['L','G','G','L','L','G'],
        ['L','G','G','G','L','L'],
        ['L','G','L','G','L','G'],
        ['L','G','L','G','G','L'],
        ['L','G','G','L','G','L'],
    ];

    public function tryDecode(array $widths): ?DecodedBarcode
    {
        // EAN-13 has 59 runs total: 3 + 24 + 5 + 24 + 3
        // But we might have leading/trailing quiet zone runs
        $start = $this->findStartGuard($widths);
        if ($start === null) {
            return null;
        }

        $pos = $start;
        $moduleWidth = $this->estimateModuleFromGuard($widths, $pos);
        $pos += 3; // Skip start guard

        // Decode left half (6 digits)
        $leftDigits = [];
        $parityPattern = [];
        for ($i = 0; $i < 6; $i++) {
            $result = $this->decodeLeftDigit($widths, $pos, $moduleWidth);
            if ($result === null) {
                return null;
            }
            [$digit, $parity] = $result;
            $leftDigits[] = $digit;
            $parityPattern[] = $parity;
            $pos += 4;
        }

        // Verify center guard
        if (!$this->verifyCenterGuard($widths, $pos, $moduleWidth)) {
            return null;
        }
        $pos += 5;

        // Decode right half (6 digits)
        $rightDigits = [];
        for ($i = 0; $i < 6; $i++) {
            $digit = $this->decodeRightDigit($widths, $pos, $moduleWidth);
            if ($digit === null) {
                return null;
            }
            $rightDigits[] = $digit;
            $pos += 4;
        }

        // Determine first digit from parity pattern
        $firstDigit = $this->determineFirstDigit($parityPattern);
        if ($firstDigit === null) {
            return null;
        }

        $digits = array_merge([$firstDigit], $leftDigits, $rightDigits);
        $payload = implode('', $digits);

        // Validate check digit
        if (!$this->validateCheckDigit($payload)) {
            return null;
        }

        return new DecodedBarcode($payload, SymbolType::Ean13);
    }

    /**
     * Find the start guard pattern (1:1:1 bar-space-bar).
     * Returns the index of the first bar of the guard, or null.
     */
    private function findStartGuard(array $widths): ?int
    {
        $len = count($widths);
        // Start guard must begin with a dark bar (even index if we assume runs start with dark)
        // Scan for a 1:1:1 ratio in 3 consecutive runs
        for ($i = 0; $i < $len - 58; $i++) {
            $w1 = $widths[$i];
            $w2 = $widths[$i + 1];
            $w3 = $widths[$i + 2];

            $avg = ($w1 + $w2 + $w3) / 3.0;
            if ($avg < 1) {
                continue;
            }

            $tolerance = $avg * 0.6;
            if (abs($w1 - $avg) < $tolerance
                && abs($w2 - $avg) < $tolerance
                && abs($w3 - $avg) < $tolerance
            ) {
                return $i;
            }
        }

        return null;
    }

    private function estimateModuleFromGuard(array $widths, int $pos): float
    {
        return ($widths[$pos] + $widths[$pos + 1] + $widths[$pos + 2]) / 3.0;
    }

    /**
     * Decode a left-half digit (L or G pattern).
     * @return array{int, string}|null [digit, 'L'|'G'] or null
     */
    private function decodeLeftDigit(array $widths, int $pos, float $moduleWidth): ?array
    {
        if ($pos + 3 >= count($widths)) {
            return null;
        }

        $runs = [$widths[$pos], $widths[$pos + 1], $widths[$pos + 2], $widths[$pos + 3]];
        $total = array_sum($runs);
        $scale = 7.0 * $moduleWidth / $total;

        $normalized = array_map(
            static fn (int $w): float => $w * $scale / $moduleWidth,
            $runs,
        );

        $bestDigit = null;
        $bestParity = null;
        $bestError = PHP_FLOAT_MAX;

        for ($d = 0; $d < 10; $d++) {
            $errorL = $this->patternError($normalized, self::L_PATTERNS[$d]);
            if ($errorL < $bestError) {
                $bestError = $errorL;
                $bestDigit = $d;
                $bestParity = 'L';
            }

            $errorG = $this->patternError($normalized, self::G_PATTERNS[$d]);
            if ($errorG < $bestError) {
                $bestError = $errorG;
                $bestDigit = $d;
                $bestParity = 'G';
            }
        }

        if ($bestError > 2.0) {
            return null;
        }

        return [$bestDigit, $bestParity];
    }

    private function decodeRightDigit(array $widths, int $pos, float $moduleWidth): ?int
    {
        if ($pos + 3 >= count($widths)) {
            return null;
        }

        $runs = [$widths[$pos], $widths[$pos + 1], $widths[$pos + 2], $widths[$pos + 3]];
        $total = array_sum($runs);
        $scale = 7.0 * $moduleWidth / $total;

        $normalized = array_map(
            static fn (int $w): float => $w * $scale / $moduleWidth,
            $runs,
        );

        $bestDigit = null;
        $bestError = PHP_FLOAT_MAX;

        for ($d = 0; $d < 10; $d++) {
            $error = $this->patternError($normalized, self::R_PATTERNS[$d]);
            if ($error < $bestError) {
                $bestError = $error;
                $bestDigit = $d;
            }
        }

        if ($bestError > 2.0) {
            return null;
        }

        return $bestDigit;
    }

    private function patternError(array $normalized, array $pattern): float
    {
        $error = 0.0;
        for ($i = 0; $i < 4; $i++) {
            $error += abs($normalized[$i] - $pattern[$i]);
        }
        return $error;
    }

    private function verifyCenterGuard(array $widths, int $pos, float $moduleWidth): bool
    {
        if ($pos + 4 >= count($widths)) {
            return false;
        }

        $total = 0;
        for ($i = 0; $i < 5; $i++) {
            $total += $widths[$pos + $i];
        }

        $expected = 5.0 * $moduleWidth;
        return abs($total - $expected) < $expected * 0.5;
    }

    /**
     * @param list<string> $parityPattern
     */
    private function determineFirstDigit(array $parityPattern): ?int
    {
        for ($d = 0; $d < 10; $d++) {
            if ($parityPattern === self::FIRST_DIGIT_ENCODING[$d]) {
                return $d;
            }
        }
        return null;
    }

    private function validateCheckDigit(string $digits): bool
    {
        if (strlen($digits) !== 13) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $weight = ($i % 2 === 0) ? 1 : 3;
            $sum += (int) $digits[$i] * $weight;
        }

        $check = (10 - ($sum % 10)) % 10;
        return $check === (int) $digits[12];
    }
}
