<?php

declare(strict_types=1);

/**
 * Result of successfully decoding a linear barcode from run-length data.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Reader\SymbolType;

final readonly class DecodedBarcode
{
    public function __construct(
        public string $payload,
        public SymbolType $type,
        public ?BarPattern $pattern = null,
    ) {}
}
