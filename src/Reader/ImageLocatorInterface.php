<?php

declare(strict_types=1);

/**
 * Contract for locating barcodes and 2D codes within raster images.
 *
 * Combines both QR/2D and linear barcode detection into a single
 * interface. Implementations may delegate to separate locators for
 * each symbology family.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

interface ImageLocatorInterface extends QrLocatorInterface, LinearLocatorInterface
{
    /**
     * Locate and decode all recognizable symbols in an image.
     *
     * @param string $imageData Raw image binary data (PNG, JPEG, etc.)
     * @return list<LocatedSymbol>
     */
    public function locate(string $imageData): array;
}
