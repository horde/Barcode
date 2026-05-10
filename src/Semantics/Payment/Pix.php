<?php

declare(strict_types=1);

/**
 * Brazilian Pix payment QR code payload.
 *
 * Pix uses EMVCo Merchant Presented Mode with Brazilian-specific MAIs.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Payment;

use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Semantics\SemanticEncoderInterface;

final class Pix implements SemanticEncoderInterface
{
    public function __construct(
        private readonly string $pixKey,
        private readonly string $merchantName,
        private readonly string $merchantCity,
        private readonly ?float $amount = null,
        private readonly string $txId = '***',
    ) {}

    public function toPayload(): string
    {
        $gui = 'br.gov.bcb.pix';
        $mai26 = '00' . sprintf('%02d', strlen($gui)) . $gui
            . '01' . sprintf('%02d', strlen($this->pixKey)) . $this->pixKey;

        $fields = '000201';
        $fields .= '010212'; // Static QR
        $fields .= '26' . sprintf('%02d', strlen($mai26)) . $mai26;
        $fields .= '52040000'; // MCC
        $fields .= '5303986'; // BRL
        if ($this->amount !== null) {
            $amtStr = sprintf('%.2f', $this->amount);
            $fields .= '54' . sprintf('%02d', strlen($amtStr)) . $amtStr;
        }
        $fields .= '5802BR';
        $fields .= '59' . sprintf('%02d', strlen($this->merchantName)) . $this->merchantName;
        $fields .= '60' . sprintf('%02d', strlen($this->merchantCity)) . $this->merchantCity;

        $addData = '05' . sprintf('%02d', strlen($this->txId)) . $this->txId;
        $fields .= '62' . sprintf('%02d', strlen($addData)) . $addData;

        $fields .= '6304';
        $crc = $this->crc16($fields);
        return substr($fields, 0, -4) . '6304' . strtoupper(sprintf('%04X', $crc));
    }

    public function recommendedEncoder(): string
    {
        return QrEncoder::class;
    }

    private function crc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return $crc;
    }
}
