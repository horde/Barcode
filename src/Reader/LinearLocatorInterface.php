<?php

declare(strict_types=1);

/**
 * Contract for locating linear (1D) barcodes within raster images.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

interface LinearLocatorInterface
{
    /**
     * Locate and decode linear barcodes in an image.
     *
     * @param string $imageData Raw image binary data (PNG, JPEG, etc.)
     * @return list<LocatedSymbol>
     */
    public function locateLinear(string $imageData): array;
}
