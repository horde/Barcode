<?php

declare(strict_types=1);

/**
 * OTP provisioning URI semantic encoder/decoder.
 *
 * Generates otpauth:// URIs for TOTP/HOTP provisioning (Google Authenticator format).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics;

use Horde\Barcode\Encoder\QrEncoder;

final class OtpProvisioning implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly string $accountName,
        private readonly string $issuer,
        private readonly string $type = 'totp',
        private readonly string $algorithm = 'SHA1',
        private readonly int $digits = 6,
        private readonly int $period = 30,
        private readonly ?int $counter = null,
    ) {}

    public function toPayload(): string
    {
        $label = rawurlencode($this->issuer) . ':' . rawurlencode($this->accountName);
        $params = [
            'secret' => $this->secret,
            'issuer' => $this->issuer,
        ];

        if ($this->algorithm !== 'SHA1') {
            $params['algorithm'] = $this->algorithm;
        }
        if ($this->digits !== 6) {
            $params['digits'] = (string) $this->digits;
        }
        if ($this->type === 'totp' && $this->period !== 30) {
            $params['period'] = (string) $this->period;
        }
        if ($this->type === 'hotp' && $this->counter !== null) {
            $params['counter'] = (string) $this->counter;
        }

        return 'otpauth://' . $this->type . '/' . $label . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with($payload, 'otpauth://');
    }

    public static function fromPayload(string $payload): static
    {
        $parts = parse_url($payload);
        $type = $parts['host'] ?? 'totp';
        $path = ltrim(urldecode($parts['path'] ?? ''), '/');
        parse_str($parts['query'] ?? '', $params);

        $issuer = $params['issuer'] ?? '';
        $accountName = $path;
        if (str_contains($path, ':')) {
            [$issuer, $accountName] = explode(':', $path, 2);
        }

        return new static(
            secret: $params['secret'] ?? '',
            accountName: $accountName,
            issuer: $issuer,
            type: $type,
            algorithm: $params['algorithm'] ?? 'SHA1',
            digits: (int) ($params['digits'] ?? 6),
            period: (int) ($params['period'] ?? 30),
            counter: isset($params['counter']) ? (int) $params['counter'] : null,
        );
    }
}
