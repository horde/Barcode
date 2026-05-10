<?php

declare(strict_types=1);

/**
 * WiFi network configuration QR code payload.
 *
 * Format: WIFI:T:<auth>;S:<ssid>;P:<password>;H:<hidden>;;
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class WiFi implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $ssid,
        private readonly string $password = '',
        private readonly string $authType = 'WPA',
        private readonly bool $hidden = false,
    ) {}

    public function toPayload(): string
    {
        $escaped = str_replace(
            ['\\', ';', ',', '"', ':'],
            ['\\\\', '\\;', '\\,', '\\"', '\\:'],
            [$this->ssid, $this->password],
        );

        $payload = 'WIFI:T:' . $this->authType . ';S:' . $escaped[0] . ';';
        if ($this->password !== '') {
            $payload .= 'P:' . $escaped[1] . ';';
        }
        if ($this->hidden) {
            $payload .= 'H:true;';
        }
        $payload .= ';';
        return $payload;
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with($payload, 'WIFI:');
    }

    public static function fromPayload(string $payload): static
    {
        $payload = substr($payload, 5); // Remove "WIFI:"
        $payload = rtrim($payload, ';');

        $params = [];
        preg_match_all('/([TSPH]):([^;]*(?:\\\\.[^;]*)*)/', $payload, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $key = $match[1];
            $value = str_replace(
                ['\\\\', '\\;', '\\,', '\\"', '\\:'],
                ['\\', ';', ',', '"', ':'],
                $match[2],
            );
            $params[$key] = $value;
        }

        return new static(
            ssid: $params['S'] ?? '',
            password: $params['P'] ?? '',
            authType: $params['T'] ?? 'WPA',
            hidden: ($params['H'] ?? '') === 'true',
        );
    }
}
