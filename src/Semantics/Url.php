<?php

declare(strict_types=1);

/**
 * URL payload for QR codes.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class Url implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $url,
    ) {}

    public function toPayload(): string
    {
        return $this->url;
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return (bool) preg_match('#^https?://#i', $payload);
    }

    public static function fromPayload(string $payload): static
    {
        return new static(url: $payload);
    }
}
