<?php

declare(strict_types=1);

/**
 * A single bar element in a linear barcode.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

final class Bar
{
    public function __construct(
        public readonly float $width,
        public readonly bool $dark,
    ) {}
}
