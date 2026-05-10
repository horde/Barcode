<?php

declare(strict_types=1);

/**
 * QR Code error correction levels per ISO/IEC 18004.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder\Qr;

enum ErrorCorrectionLevel: int
{
    case L = 0;
    case M = 1;
    case Q = 2;
    case H = 3;

    public function formatBits(): int
    {
        return match ($this) {
            self::L => 0b01,
            self::M => 0b00,
            self::Q => 0b11,
            self::H => 0b10,
        };
    }
}
