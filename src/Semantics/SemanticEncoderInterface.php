<?php

declare(strict_types=1);

/**
 * Contract for semantic encoders that produce barcode payload strings.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

interface SemanticEncoderInterface
{
    /**
     * Produce the raw payload string for encoding into a barcode.
     */
    public function toPayload(): string;

    /**
     * Recommended symbology class name for this semantic format.
     *
     * @return class-string
     */
    public function recommendedEncoder(): string;
}
