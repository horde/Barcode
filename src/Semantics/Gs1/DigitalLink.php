<?php

declare(strict_types=1);

/**
 * GS1 Digital Link URI encoder/decoder.
 *
 * Encodes GS1 identifiers as web-resolvable URLs per GS1 Digital Link standard.
 * Example: https://id.gs1.org/01/09501101530003/17/240101
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Gs1;

use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class DigitalLink implements SemanticEncoderInterface, SemanticDecoderInterface
{
    private const DEFAULT_DOMAIN = 'https://id.gs1.org';

    /**
     * @param array<string, string> $identifiers AI => value pairs (primary + qualifiers)
     * @param string $domain Base resolver domain
     */
    public function __construct(
        private readonly array $identifiers,
        private readonly string $domain = self::DEFAULT_DOMAIN,
    ) {}

    public function toPayload(): string
    {
        $path = '';
        foreach ($this->identifiers as $ai => $value) {
            $path .= '/' . $ai . '/' . rawurlencode($value);
        }
        return rtrim($this->domain, '/') . $path;
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return (bool) preg_match('#^https?://.*/(01|8006|8010|8013|8017|8018)/#', $payload);
    }

    public static function fromPayload(string $payload): static
    {
        $parts = parse_url($payload);
        $domain = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'id.gs1.org');
        $path = $parts['path'] ?? '';

        $segments = explode('/', trim($path, '/'));
        $identifiers = [];
        for ($i = 0; $i + 1 < count($segments); $i += 2) {
            $identifiers[$segments[$i]] = rawurldecode($segments[$i + 1]);
        }

        return new static(identifiers: $identifiers, domain: $domain);
    }

    /**
     * @return array<string, string>
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
}
