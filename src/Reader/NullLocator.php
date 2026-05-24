<?php

declare(strict_types=1);

/**
 * No-op locator that never finds any symbols.
 *
 * Useful as a default fallback or for testing consumers that accept
 * an ImageLocatorInterface without requiring a real detection backend.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

final class NullLocator implements ImageLocatorInterface
{
    /** @return list<LocatedSymbol> */
    public function locate(string $imageData): array
    {
        return [];
    }

    /** @return list<LocatedSymbol> */
    public function locateQr(string $imageData): array
    {
        return [];
    }

    /** @return list<LocatedSymbol> */
    public function locateLinear(string $imageData): array
    {
        return [];
    }
}
