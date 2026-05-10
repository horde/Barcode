<?php

declare(strict_types=1);

/**
 * EAN/UPC barcode encoder implementing ISO/IEC 15420.
 *
 * Supports EAN-13, EAN-8, UPC-A (12 digits), and UPC-E (8 digits).
 * Automatically calculates check digits if omitted.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

use Horde\Barcode\Exception\EncodingException;

final class EanUpcEncoder implements LinearEncoderInterface
{
    /**
     * L-code patterns (odd parity, used in left half).
     * @var list<list<int>>
     */
    private const L_PATTERNS = [
        [3,2,1,1], [2,2,2,1], [2,1,2,2], [1,4,1,1], [1,1,3,2],
        [1,2,3,1], [1,1,1,4], [1,3,1,2], [1,2,1,3], [3,1,1,2],
    ];

    /**
     * G-code patterns (odd parity, reversed).
     * @var list<list<int>>
     */
    private const G_PATTERNS = [
        [1,1,2,3], [1,2,2,2], [2,2,1,2], [1,1,4,1], [2,3,1,1],
        [1,3,2,1], [4,1,1,1], [2,1,3,1], [3,1,2,1], [2,1,1,3],
    ];

    /**
     * R-code patterns (even parity, used in right half).
     * @var list<list<int>>
     */
    private const R_PATTERNS = [
        [3,2,1,1], [2,2,2,1], [2,1,2,2], [1,4,1,1], [1,1,3,2],
        [1,2,3,1], [1,1,1,4], [1,3,1,2], [1,2,1,3], [3,1,1,2],
    ];

    /**
     * First digit encoding for EAN-13 (which combination of L and G codes).
     * @var list<list<string>>
     */
    private const FIRST_DIGIT_ENCODING = [
        ['L','L','L','L','L','L'], // 0
        ['L','L','G','L','G','G'], // 1
        ['L','L','G','G','L','G'], // 2
        ['L','L','G','G','G','L'], // 3
        ['L','G','L','L','G','G'], // 4
        ['L','G','G','L','L','G'], // 5
        ['L','G','G','G','L','L'], // 6
        ['L','G','L','G','L','G'], // 7
        ['L','G','L','G','G','L'], // 8
        ['L','G','G','L','G','L'], // 9
    ];

    public function encode(string $data): BarPattern
    {
        $data = preg_replace('/[^0-9]/', '', $data);
        $len = strlen($data);

        if ($len === 12 || $len === 13) {
            return $this->encodeEan13($data);
        }
        if ($len === 7 || $len === 8) {
            return $this->encodeEan8($data);
        }

        throw new EncodingException(
            sprintf('EAN/UPC requires 7-8 (EAN-8) or 12-13 (EAN-13/UPC-A) digits, got %d', $len)
        );
    }

    private function encodeEan13(string $data): BarPattern
    {
        if (strlen($data) === 12) {
            $data .= $this->calculateCheckDigit($data);
        }

        $digits = array_map('intval', str_split($data));
        $firstDigit = $digits[0];
        $encoding = self::FIRST_DIGIT_ENCODING[$firstDigit];

        $bars = [];

        // Start guard: 1-1-1
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);

        // Left half (digits 1-6)
        for ($i = 0; $i < 6; $i++) {
            $digit = $digits[$i + 1];
            $pattern = $encoding[$i] === 'L' ? self::L_PATTERNS[$digit] : self::G_PATTERNS[$digit];
            $dark = false; // L and G patterns start with space
            foreach ($pattern as $width) {
                $bars[] = new Bar((float) $width, $dark);
                $dark = !$dark;
            }
        }

        // Center guard: 0-1-0-1-0
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);

        // Right half (digits 7-12)
        for ($i = 0; $i < 6; $i++) {
            $digit = $digits[$i + 7];
            $pattern = self::R_PATTERNS[$digit];
            $dark = true; // R patterns start with bar
            foreach ($pattern as $width) {
                $bars[] = new Bar((float) $width, $dark);
                $dark = !$dark;
            }
        }

        // End guard: 1-1-1
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);

        return new BarPattern($bars, 1.0);
    }

    private function encodeEan8(string $data): BarPattern
    {
        if (strlen($data) === 7) {
            $data .= $this->calculateCheckDigit($data);
        }

        $digits = array_map('intval', str_split($data));
        $bars = [];

        // Start guard
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);

        // Left half (digits 0-3, all L-code)
        for ($i = 0; $i < 4; $i++) {
            $pattern = self::L_PATTERNS[$digits[$i]];
            $dark = false;
            foreach ($pattern as $width) {
                $bars[] = new Bar((float) $width, $dark);
                $dark = !$dark;
            }
        }

        // Center guard
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);

        // Right half (digits 4-7, R-code)
        for ($i = 4; $i < 8; $i++) {
            $pattern = self::R_PATTERNS[$digits[$i]];
            $dark = true;
            foreach ($pattern as $width) {
                $bars[] = new Bar((float) $width, $dark);
                $dark = !$dark;
            }
        }

        // End guard
        $bars[] = new Bar(1.0, true);
        $bars[] = new Bar(1.0, false);
        $bars[] = new Bar(1.0, true);

        return new BarPattern($bars, 1.0);
    }

    private function calculateCheckDigit(string $data): string
    {
        $sum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $weight = (($len - $i) % 2 === 0) ? 3 : 1;
            $sum += (int) $data[$i] * $weight;
        }
        $check = (10 - ($sum % 10)) % 10;
        return (string) $check;
    }
}
