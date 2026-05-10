<?php

declare(strict_types=1);

/**
 * QR Code data encoding modes per ISO/IEC 18004.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder\Qr;

enum EncodingMode: int
{
    case Numeric = 0b0001;
    case Alphanumeric = 0b0010;
    case Byte = 0b0100;
    case Kanji = 0b1000;

    public function characterCountBits(int $version): int
    {
        if ($version <= 9) {
            return match ($this) {
                self::Numeric => 10,
                self::Alphanumeric => 9,
                self::Byte => 8,
                self::Kanji => 8,
            };
        }
        if ($version <= 26) {
            return match ($this) {
                self::Numeric => 12,
                self::Alphanumeric => 11,
                self::Byte => 16,
                self::Kanji => 10,
            };
        }
        return match ($this) {
            self::Numeric => 14,
            self::Alphanumeric => 13,
            self::Byte => 16,
            self::Kanji => 12,
        };
    }
}
