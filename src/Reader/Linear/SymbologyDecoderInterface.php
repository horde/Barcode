<?php

declare(strict_types=1);

/**
 * Contract for symbology-specific barcode decoders.
 *
 * Each decoder attempts to interpret a normalized run-length sequence
 * as a specific barcode format. Returns null if the sequence does not
 * match the expected symbology structure.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader\Linear;

interface SymbologyDecoderInterface
{
    /**
     * Attempt to decode a run-length sequence.
     *
     * @param list<int> $widths Raw pixel widths of alternating dark/light runs (starts with dark)
     * @return DecodedBarcode|null Decoded result or null if not this symbology
     */
    public function tryDecode(array $widths): ?DecodedBarcode;
}
