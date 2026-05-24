<?php

declare(strict_types=1);

/**
 * A barcode or 2D code detected within an image.
 *
 * Combines the bounding box location, decoded payload, symbology type,
 * and optionally the raw symbol structure (ModuleMatrix or BarPattern).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\ModuleMatrix;

final readonly class LocatedSymbol
{
    public function __construct(
        public BoundingBox $bounds,
        public string $payload,
        public SymbolType $type,
        public ModuleMatrix|BarPattern|null $symbol = null,
    ) {}
}
