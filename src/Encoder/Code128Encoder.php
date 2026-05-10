<?php

declare(strict_types=1);

/**
 * Code 128 barcode encoder implementing ISO/IEC 15417.
 *
 * Supports character sets A, B, and C with automatic optimal switching.
 * Includes check digit calculation and start/stop patterns.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

use Horde\Barcode\Exception\EncodingException;

final class Code128Encoder implements LinearEncoderInterface
{
    private const START_A = 103;
    private const START_B = 104;
    private const START_C = 105;
    private const CODE_A = 101;
    private const CODE_B = 100;
    private const CODE_C = 99;
    private const STOP = 106;

    /**
     * Bar patterns for each symbol value (0-106).
     * Each pattern is 6 elements: alternating bar/space widths (always starts with bar).
     *
     * @var list<list<int>>
     */
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

    public function encode(string $data): BarPattern
    {
        if ($data === '') {
            throw new EncodingException('Data must not be empty for Code 128');
        }

        $symbols = $this->buildSymbolSequence($data);
        $checkDigit = $this->calculateCheckDigit($symbols);
        $symbols[] = $checkDigit;
        $symbols[] = self::STOP;

        $bars = [];
        foreach ($symbols as $symbol) {
            $pattern = self::PATTERNS[$symbol];
            $dark = true;
            foreach ($pattern as $width) {
                $bars[] = new Bar((float) $width, $dark);
                $dark = !$dark;
            }
        }

        return new BarPattern($bars, 1.0);
    }

    /**
     * @return list<int> Symbol values including start code
     */
    private function buildSymbolSequence(string $data): array
    {
        $len = strlen($data);
        $pos = 0;
        $symbols = [];
        $currentSet = null;

        while ($pos < $len) {
            // Check if we should use Code C (consecutive digit pairs)
            $remainingDigits = $this->countLeadingDigits($data, $pos);

            if ($currentSet === null) {
                // Choose initial start code
                if ($remainingDigits >= 4) {
                    $symbols[] = self::START_C;
                    $currentSet = 'C';
                } elseif (ord($data[$pos]) < 32) {
                    $symbols[] = self::START_A;
                    $currentSet = 'A';
                } else {
                    $symbols[] = self::START_B;
                    $currentSet = 'B';
                }
            } else {
                // Switch code set if needed
                if ($currentSet !== 'C' && $remainingDigits >= 4) {
                    $symbols[] = self::CODE_C;
                    $currentSet = 'C';
                } elseif ($currentSet === 'C' && $remainingDigits < 2) {
                    if (ord($data[$pos]) < 32) {
                        $symbols[] = self::CODE_A;
                        $currentSet = 'A';
                    } else {
                        $symbols[] = self::CODE_B;
                        $currentSet = 'B';
                    }
                } elseif ($currentSet === 'A' && ord($data[$pos]) >= 96) {
                    $symbols[] = self::CODE_B;
                    $currentSet = 'B';
                } elseif ($currentSet === 'B' && ord($data[$pos]) < 32) {
                    $symbols[] = self::CODE_A;
                    $currentSet = 'A';
                }
            }

            // Encode character(s)
            if ($currentSet === 'C') {
                $pair = (int) substr($data, $pos, 2);
                $symbols[] = $pair;
                $pos += 2;
            } elseif ($currentSet === 'A') {
                $ascii = ord($data[$pos]);
                if ($ascii < 32) {
                    $symbols[] = $ascii + 64;
                } elseif ($ascii < 96) {
                    $symbols[] = $ascii - 32;
                } else {
                    $symbols[] = self::CODE_B;
                    $currentSet = 'B';
                    $symbols[] = $ascii - 32;
                }
                $pos++;
            } else {
                $ascii = ord($data[$pos]);
                if ($ascii >= 32 && $ascii < 128) {
                    $symbols[] = $ascii - 32;
                } else {
                    $symbols[] = self::CODE_A;
                    $currentSet = 'A';
                    if ($ascii < 32) {
                        $symbols[] = $ascii + 64;
                    } else {
                        $symbols[] = $ascii - 32;
                    }
                }
                $pos++;
            }
        }

        return $symbols;
    }

    private function countLeadingDigits(string $data, int $pos): int
    {
        $count = 0;
        $len = strlen($data);
        while ($pos + $count < $len && $data[$pos + $count] >= '0' && $data[$pos + $count] <= '9') {
            $count++;
        }
        return $count;
    }

    /**
     * @param list<int> $symbols
     */
    private function calculateCheckDigit(array $symbols): int
    {
        $sum = $symbols[0];
        for ($i = 1; $i < count($symbols); $i++) {
            $sum += $symbols[$i] * $i;
        }
        return $sum % 103;
    }
}
