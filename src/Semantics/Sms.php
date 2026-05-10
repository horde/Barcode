<?php

declare(strict_types=1);

/**
 * SMS (smsto:) URI payload.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class Sms implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $number,
        private readonly string $message = '',
    ) {}

    public function toPayload(): string
    {
        $uri = 'smsto:' . $this->number;
        if ($this->message !== '') {
            $uri .= ':' . $this->message;
        }
        return $uri;
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with(strtolower($payload), 'smsto:');
    }

    public static function fromPayload(string $payload): static
    {
        $content = substr($payload, 6);
        $parts = explode(':', $content, 2);
        return new static(
            number: $parts[0],
            message: $parts[1] ?? '',
        );
    }
}
