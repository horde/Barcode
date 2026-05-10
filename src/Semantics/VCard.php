<?php

declare(strict_types=1);

/**
 * vCard/MeCard contact sharing QR code payload.
 *
 * Supports MeCard format (compact, widely used by QR readers).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class VCard implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $phone = '',
        private readonly string $email = '',
        private readonly string $url = '',
        private readonly string $address = '',
        private readonly string $organization = '',
        private readonly string $note = '',
    ) {}

    public function toPayload(): string
    {
        $parts = ['MECARD:N:' . $this->escape($this->name)];
        if ($this->phone !== '') {
            $parts[] = 'TEL:' . $this->escape($this->phone);
        }
        if ($this->email !== '') {
            $parts[] = 'EMAIL:' . $this->escape($this->email);
        }
        if ($this->url !== '') {
            $parts[] = 'URL:' . $this->escape($this->url);
        }
        if ($this->address !== '') {
            $parts[] = 'ADR:' . $this->escape($this->address);
        }
        if ($this->organization !== '') {
            $parts[] = 'ORG:' . $this->escape($this->organization);
        }
        if ($this->note !== '') {
            $parts[] = 'NOTE:' . $this->escape($this->note);
        }
        return implode(';', $parts) . ';;';
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with($payload, 'MECARD:');
    }

    public static function fromPayload(string $payload): static
    {
        $payload = substr($payload, 7); // Remove "MECARD:"
        $payload = rtrim($payload, ';');

        $params = [];
        preg_match_all('/([A-Z]+):([^;]*(?:\\\\.[^;]*)*)/', $payload, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1];
            $value = str_replace(['\\;', '\\\\'], [';', '\\'], $match[2]);
            $params[$key] = $value;
        }

        return new static(
            name: $params['N'] ?? '',
            phone: $params['TEL'] ?? '',
            email: $params['EMAIL'] ?? '',
            url: $params['URL'] ?? '',
            address: $params['ADR'] ?? '',
            organization: $params['ORG'] ?? '',
            note: $params['NOTE'] ?? '',
        );
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';'], ['\\\\', '\\;'], $value);
    }
}
