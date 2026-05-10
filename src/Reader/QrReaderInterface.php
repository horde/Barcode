<?php

declare(strict_types=1);

/**
 * Contract for QR code readers.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;

interface QrReaderInterface
{
    public function decode(ModuleMatrix $matrix): string;
}
