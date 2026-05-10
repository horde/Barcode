<?php

declare(strict_types=1);

/**
 * SEPA EPC QR code payment payload.
 *
 * Generates European Payments Council (EPC) Quick Response Code format
 * for SEPA Credit Transfers per EPC069-12.
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

final class SepaEpc implements SemanticEncoderInterface, SemanticDecoderInterface
{
    public function __construct(
        private readonly string $beneficiaryName,
        private readonly string $iban,
        private readonly float $amount,
        private readonly string $currency = 'EUR',
        private readonly string $bic = '',
        private readonly string $reference = '',
        private readonly string $remittanceText = '',
        private readonly string $information = '',
    ) {}

    public function toPayload(): string
    {
        $lines = [
            'BCD',                          // Service Tag
            '002',                          // Version
            '1',                            // Character set (UTF-8)
            'SCT',                          // Identification
            $this->bic,                     // BIC
            $this->beneficiaryName,         // Beneficiary Name
            $this->iban,                    // IBAN
            $this->currency . sprintf('%.2f', $this->amount), // Amount
            '',                             // Purpose
            $this->reference,               // Remittance Reference
            $this->remittanceText,          // Remittance Text
            $this->information,             // Information
        ];

        return implode("\n", $lines);
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with($payload, "BCD\n");
    }

    public static function fromPayload(string $payload): static
    {
        $lines = explode("\n", $payload);
        $amountStr = $lines[7] ?? '';
        $currency = substr($amountStr, 0, 3);
        $amount = (float) substr($amountStr, 3);

        return new static(
            beneficiaryName: $lines[5] ?? '',
            iban: $lines[6] ?? '',
            amount: $amount,
            currency: $currency ?: 'EUR',
            bic: $lines[4] ?? '',
            reference: $lines[9] ?? '',
            remittanceText: $lines[10] ?? '',
            information: $lines[11] ?? '',
        );
    }
}
