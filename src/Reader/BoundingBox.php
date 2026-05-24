<?php

declare(strict_types=1);

/**
 * Axis-aligned bounding box for a detected symbol within an image.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

final readonly class BoundingBox
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {}
}
