<?php

declare(strict_types=1);

/**
 * Indian UPI (Unified Payments Interface) QR code payload.
 *
 * Format: upi://pay?pa=<vpa>&pn=<name>&am=<amount>&cu=<currency>&tn=<note>
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Payment;

use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class Upi implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $vpa,
        private readonly string $payeeName,
        private readonly ?float $amount = null,
        private readonly string $currency = 'INR',
        private readonly string $transactionNote = '',
        private readonly string $merchantCode = '',
    ) {}

    public function toPayload(): string
    {
        $params = [
            'pa' => $this->vpa,
            'pn' => $this->payeeName,
        ];
        if ($this->amount !== null) {
            $params['am'] = sprintf('%.2f', $this->amount);
        }
        $params['cu'] = $this->currency;
        if ($this->transactionNote !== '') {
            $params['tn'] = $this->transactionNote;
        }
        if ($this->merchantCode !== '') {
            $params['mc'] = $this->merchantCode;
        }

        return 'upi://pay?' . http_build_query($params, '', '&');
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with(strtolower($payload), 'upi://pay');
    }

    public static function fromPayload(string $payload): static
    {
        $parts = parse_url($payload);
        parse_str($parts['query'] ?? '', $params);

        return new static(
            vpa: $params['pa'] ?? '',
            payeeName: $params['pn'] ?? '',
            amount: isset($params['am']) ? (float) $params['am'] : null,
            currency: $params['cu'] ?? 'INR',
            transactionNote: $params['tn'] ?? '',
            merchantCode: $params['mc'] ?? '',
        );
    }
}
