<?php

declare(strict_types=1);

/**
 * EMVCo Merchant Presented QR Code payload.
 *
 * Implements the EMV QR Code Specification for Payment Systems (merchant presented).
 * Uses TLV (Tag-Length-Value) encoding.
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

final class EmvQr implements SemanticEncoderInterface, SemanticDecoderInterface
{
    /**
     * @param array<string, string> $fields Tag => value pairs (TLV without CRC)
     */
    public function __construct(
        private readonly array $fields,
    ) {}

    public static function create(
        string $merchantName,
        string $merchantCity,
        string $merchantId,
        ?float $amount = null,
        string $currency = '840',
        string $countryCode = 'US',
    ): self {
        /** @var array<string, string> $fields */
        $fields = [
            '00' => '01',                   // Payload Format Indicator
            '01' => $amount === null ? '12' : '11', // Point of Initiation
            '52' => '0000',                 // MCC
            '53' => $currency,              // Transaction Currency
            '58' => $countryCode,           // Country Code
            '59' => $merchantName,          // Merchant Name
            '60' => $merchantCity,          // Merchant City
            '26' => '00' . sprintf('%02d', strlen($merchantId)) . $merchantId, // Merchant Account
        ];
        if ($amount !== null) {
            $fields['54'] = sprintf('%.2f', $amount);
        }
        return new self($fields);
    }

    public function toPayload(): string
    {
        $payload = '';
        foreach ($this->fields as $tag => $value) {
            $payload .= $tag . sprintf('%02d', strlen($value)) . $value;
        }
        // CRC placeholder then calculate
        $payload .= '6304';
        $crc = $this->crc16($payload);
        $payload .= strtoupper(sprintf('%04X', $crc));
        return substr($payload, 0, -8) . '6304' . strtoupper(sprintf('%04X', $crc));
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with($payload, '000201');
    }

    public static function fromPayload(string $payload): static
    {
        $fields = [];
        $pos = 0;
        $len = strlen($payload);

        while ($pos + 4 <= $len) {
            $tag = substr($payload, $pos, 2);
            $fieldLen = (int) substr($payload, $pos + 2, 2);
            $pos += 4;
            if ($pos + $fieldLen > $len) {
                break;
            }
            $value = substr($payload, $pos, $fieldLen);
            $fields[$tag] = $value;
            $pos += $fieldLen;
        }

        return new static($fields);
    }

    private function crc16(string $data): int
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }
                $crc &= 0xFFFF;
            }
        }
        return $crc;
    }
}
