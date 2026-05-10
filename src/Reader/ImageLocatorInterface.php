<?php

declare(strict_types=1);

/**
 * Contract for locating barcodes within raster images.
 *
 * Implementations use image processing to find barcode symbols in
 * photographs or scanned documents and return their module matrices.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;

interface ImageLocatorInterface
{
    /**
     * Locate and extract barcode module matrices from an image.
     *
     * @param string $imageData Raw image binary data (PNG, JPEG, etc.)
     * @return list<ModuleMatrix> Zero or more located symbols
     */
    public function locate(string $imageData): array;
}
