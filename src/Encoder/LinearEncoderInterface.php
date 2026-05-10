<?php

declare(strict_types=1);

/**
 * Contract for linear (1D) barcode encoders (Code 128, EAN/UPC, Code 39).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

interface LinearEncoderInterface extends EncoderInterface
{
    public function encode(string $data): BarPattern;
}
