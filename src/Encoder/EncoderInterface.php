<?php

declare(strict_types=1);

/**
 * Common contract for all barcode encoders.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

interface EncoderInterface
{
    public function encode(string $data): ModuleMatrix|BarPattern;
}
