<?php

declare(strict_types=1);

/**
 * Telephone (tel:) URI payload.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class Tel implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $number,
    ) {}

    public function toPayload(): string
    {
        return 'tel:' . $this->number;
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with(strtolower($payload), 'tel:');
    }

    public static function fromPayload(string $payload): static
    {
        return new static(number: substr($payload, 4));
    }
}
