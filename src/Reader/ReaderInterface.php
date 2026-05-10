<?php

declare(strict_types=1);

/**
 * Contract for barcode readers that decode symbol data.
 *
 * Accepts a ModuleMatrix or BarPattern and returns the decoded payload string.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\ModuleMatrix;

interface ReaderInterface
{
    public function decode(ModuleMatrix|BarPattern $symbol): string;
}
