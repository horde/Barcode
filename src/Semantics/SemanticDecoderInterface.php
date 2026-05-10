<?php

declare(strict_types=1);

/**
 * Contract for semantic decoders that parse barcode payload strings.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

interface SemanticDecoderInterface
{
    /**
     * Determine if this decoder can handle the given raw payload.
     */
    public static function canDecode(string $payload): bool;

    /**
     * Parse a raw payload into a structured semantic object.
     */
    public static function fromPayload(string $payload): static;
}
