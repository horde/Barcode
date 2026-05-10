<?php

declare(strict_types=1);

/**
 * Code 39 barcode encoder implementing ISO/IEC 16388.
 *
 * Supports uppercase letters, digits, and special characters (-.$/+% space).
 * Automatically wraps data with start/stop characters (*).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

use Horde\Barcode\Exception\EncodingException;

final class Code39Encoder implements LinearEncoderInterface
{
    /**
     * Character to bar/space pattern mapping.
     * N = narrow (1), W = wide (3), alternating bar/space starting with bar.
     * Each character is 9 elements (5 bars + 4 spaces).
     */
    private const PATTERNS = [
        '0' => [1,1,1,3,3,1,3,1,1],
        '1' => [3,1,1,3,1,1,1,1,3],
        '2' => [1,1,3,3,1,1,1,1,3],
        '3' => [3,1,3,3,1,1,1,1,1],
        '4' => [1,1,1,3,3,1,1,1,3],
        '5' => [3,1,1,3,3,1,1,1,1],
        '6' => [1,1,3,3,3,1,1,1,1],
        '7' => [1,1,1,3,1,1,3,1,3],
        '8' => [3,1,1,3,1,1,3,1,1],
        '9' => [1,1,3,3,1,1,3,1,1],
        'A' => [3,1,1,1,1,3,1,1,3],
        'B' => [1,1,3,1,1,3,1,1,3],
        'C' => [3,1,3,1,1,3,1,1,1],
        'D' => [1,1,1,1,3,3,1,1,3],
        'E' => [3,1,1,1,3,3,1,1,1],
        'F' => [1,1,3,1,3,3,1,1,1],
        'G' => [1,1,1,1,1,3,3,1,3],
        'H' => [3,1,1,1,1,3,3,1,1],
        'I' => [1,1,3,1,1,3,3,1,1],
        'J' => [1,1,1,1,3,3,3,1,1],
        'K' => [3,1,1,1,1,1,1,3,3],
        'L' => [1,1,3,1,1,1,1,3,3],
        'M' => [3,1,3,1,1,1,1,3,1],
        'N' => [1,1,1,1,3,1,1,3,3],
        'O' => [3,1,1,1,3,1,1,3,1],
        'P' => [1,1,3,1,3,1,1,3,1],
        'Q' => [1,1,1,1,1,1,3,3,3],
        'R' => [3,1,1,1,1,1,3,3,1],
        'S' => [1,1,3,1,1,1,3,3,1],
        'T' => [1,1,1,1,3,1,3,3,1],
        'U' => [3,3,1,1,1,1,1,1,3],
        'V' => [1,3,3,1,1,1,1,1,3],
        'W' => [3,3,3,1,1,1,1,1,1],
        'X' => [1,3,1,1,3,1,1,1,3],
        'Y' => [3,3,1,1,3,1,1,1,1],
        'Z' => [1,3,3,1,3,1,1,1,1],
        '-' => [1,3,1,1,1,1,3,1,3],
        '.' => [3,3,1,1,1,1,3,1,1],
        ' ' => [1,3,3,1,1,1,3,1,1],
        '$' => [1,3,1,3,1,3,1,1,1],
        '/' => [1,3,1,3,1,1,1,3,1],
        '+' => [1,3,1,1,1,3,1,3,1],
        '%' => [1,1,1,3,1,3,1,3,1],
        '*' => [1,3,1,1,3,1,3,1,1],
    ];

    public function encode(string $data): BarPattern
    {
        $data = strtoupper($data);

        if ($data === '') {
            throw new EncodingException('Data must not be empty for Code 39');
        }

        for ($i = 0; $i < strlen($data); $i++) {
            if (!isset(self::PATTERNS[$data[$i]])) {
                throw new EncodingException(
                    sprintf('Character "%s" not valid for Code 39', $data[$i])
                );
            }
        }

        $bars = [];
        $chars = '*' . $data . '*';

        for ($charIdx = 0; $charIdx < strlen($chars); $charIdx++) {
            if ($charIdx > 0) {
                // Inter-character gap (narrow space)
                $bars[] = new Bar(1.0, false);
            }
            $pattern = self::PATTERNS[$chars[$charIdx]];
            $dark = true;
            foreach ($pattern as $width) {
                $bars[] = new Bar((float) $width, $dark);
                $dark = !$dark;
            }
        }

        return new BarPattern($bars, 1.0);
    }
}
