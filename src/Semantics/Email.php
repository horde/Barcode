<?php

declare(strict_types=1);

/**
 * Email (mailto:) URI payload.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class Email implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $address,
        private readonly string $subject = '',
        private readonly string $body = '',
    ) {}

    public function toPayload(): string
    {
        $uri = 'mailto:' . $this->address;
        $params = [];
        if ($this->subject !== '') {
            $params['subject'] = $this->subject;
        }
        if ($this->body !== '') {
            $params['body'] = $this->body;
        }
        if ($params !== []) {
            $uri .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        return $uri;
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with(strtolower($payload), 'mailto:');
    }

    public static function fromPayload(string $payload): static
    {
        $parts = parse_url($payload);
        $address = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $params);
        return new static(
            address: $address,
            subject: $params['subject'] ?? '',
            body: $params['body'] ?? '',
        );
    }
}
