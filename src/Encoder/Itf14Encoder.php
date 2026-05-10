<?php

declare(strict_types=1);

/**
 * Interleaved 2 of 5 (ITF) barcode encoder per ISO/IEC 16390.
 *
 * Encodes numeric data in pairs. If the data has an odd number of digits,
 * a leading zero is prepended. ITF-14 is a 14-digit variant commonly used
 * on shipping cartons.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

use Horde\Barcode\Exception\EncodingException;

final class Itf14Encoder implements LinearEncoderInterface
{
    private const NARROW = 1.0;
    private const WIDE = 3.0;

    /**
     * Bar width patterns for digits 0-9.
     * N = narrow, W = wide (5 elements per digit).
     */
    private const DIGIT_PATTERNS = [
        [1, 1, 3, 3, 1], // 0: NNWWN
        [3, 1, 1, 1, 3], // 1: WNNWN -> actually WNNNW
        [1, 3, 1, 1, 3], // 2: NWNNW
        [3, 3, 1, 1, 1], // 3: WWNNN
        [1, 1, 3, 1, 3], // 4: NNWNW
        [3, 1, 3, 1, 1], // 5: WNWNN
        [1, 3, 3, 1, 1], // 6: NWWNN
        [1, 1, 1, 3, 3], // 7: NNNWW
        [3, 1, 1, 3, 1], // 8: WNNWN
        [1, 3, 1, 3, 1], // 9: NWNWN
    ];

    public function encode(string $data): BarPattern
    {
        if (!preg_match('/^\d+$/', $data)) {
            throw new EncodingException('ITF/ITF-14 only encodes numeric data');
        }

        if ($data === '') {
            throw new EncodingException('Data must not be empty for ITF');
        }

        // Pad to even length
        if (strlen($data) % 2 !== 0) {
            $data = '0' . $data;
        }

        $bars = [];

        // Start pattern: narrow bar, narrow space, narrow bar, narrow space
        $bars[] = new Bar(self::NARROW, true);
        $bars[] = new Bar(self::NARROW, false);
        $bars[] = new Bar(self::NARROW, true);
        $bars[] = new Bar(self::NARROW, false);

        // Encode digit pairs
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 2) {
            $d1 = (int) $data[$i];
            $d2 = (int) $data[$i + 1];
            $barsPattern = self::DIGIT_PATTERNS[$d1];
            $spacesPattern = self::DIGIT_PATTERNS[$d2];

            for ($j = 0; $j < 5; $j++) {
                $bars[] = new Bar($barsPattern[$j], true);
                $bars[] = new Bar($spacesPattern[$j], false);
            }
        }

        // Stop pattern: wide bar, narrow space, narrow bar
        $bars[] = new Bar(self::WIDE, true);
        $bars[] = new Bar(self::NARROW, false);
        $bars[] = new Bar(self::NARROW, true);

        return new BarPattern($bars, 1.0);
    }
}
