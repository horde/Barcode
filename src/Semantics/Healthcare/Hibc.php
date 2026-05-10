<?php

declare(strict_types=1);

/**
 * Health Industry Bar Code (HIBC) payload encoder/decoder.
 *
 * Implements ANSI/HIBC 2.6 standard for healthcare product identification.
 * HIBC uses a primary (LIC + product code) and secondary (date/lot/serial) structure.
 * Typically encoded as Code 128 or Code 39.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Healthcare;

use Horde\Barcode\Encoder\Code128Encoder;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class Hibc implements SemanticEncoderInterface, SemanticDecoderInterface
{
    /**
     * @param string $lic Labeler Identification Code (4 chars: letter + 3 alphanum)
     * @param string $productCode Product or catalog number (up to 18 chars)
     * @param int $unitOfMeasure Unit of measure (0-9)
     * @param string $expirationDate MMDDYY or YYMMDDHH format
     * @param string $lotNumber Lot/batch number
     * @param string $serialNumber Serial number
     * @param int $quantity Quantity (0 = not specified)
     */
    public function __construct(
        private readonly string $lic,
        private readonly string $productCode,
        private readonly int $unitOfMeasure = 0,
        private readonly string $expirationDate = '',
        private readonly string $lotNumber = '',
        private readonly string $serialNumber = '',
        private readonly int $quantity = 0,
    ) {}

    public function toPayload(): string
    {
        $primary = '+' . strtoupper($this->lic) . strtoupper($this->productCode)
            . (string) $this->unitOfMeasure;
        $primary .= $this->checkCharacter($primary);

        if ($this->expirationDate === '' && $this->lotNumber === '' && $this->serialNumber === '') {
            return $primary;
        }

        $secondary = '+$$';
        if ($this->expirationDate !== '') {
            $secondary .= $this->expirationDate;
        }
        if ($this->lotNumber !== '') {
            $secondary .= $this->lotNumber;
        }
        if ($this->serialNumber !== '') {
            $secondary .= '/S' . $this->serialNumber;
        }
        if ($this->quantity > 0) {
            $secondary .= '/Q' . $this->quantity;
        }
        $secondary .= $this->checkCharacter($secondary);

        return $primary . $secondary;
    }

    public function recommendedEncoder(): string
    {
        return Code128Encoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        return str_starts_with($payload, '+') && strlen($payload) >= 6
            && ctype_alpha($payload[1]);
    }

    public static function fromPayload(string $payload): static
    {
        $parts = explode('+$$', $payload, 2);
        $primary = ltrim($parts[0], '+');

        $check = substr($primary, -1);
        $primary = substr($primary, 0, -1);

        $lic = substr($primary, 0, 4);
        $uom = (int) substr($primary, -1);
        $product = substr($primary, 4, -1);

        $expiry = '';
        $lot = '';
        $serial = '';
        $qty = 0;

        if (isset($parts[1])) {
            $secondary = $parts[1];
            $secondary = substr($secondary, 0, -1);

            $serialPos = strpos($secondary, '/S');
            $qtyPos = strpos($secondary, '/Q');

            if ($qtyPos !== false) {
                $qty = (int) substr($secondary, $qtyPos + 2);
                $secondary = substr($secondary, 0, $qtyPos);
            }
            if ($serialPos !== false) {
                $serial = substr($secondary, $serialPos + 2);
                $secondary = substr($secondary, 0, $serialPos);
            }

            if (preg_match('/^(\d{6,8})(.*)$/', $secondary, $m)) {
                $expiry = $m[1];
                $lot = $m[2];
            } else {
                $lot = $secondary;
            }
        }

        return new static(
            lic: $lic,
            productCode: $product,
            unitOfMeasure: $uom,
            expirationDate: $expiry,
            lotNumber: $lot,
            serialNumber: $serial,
            quantity: $qty,
        );
    }

    private function checkCharacter(string $data): string
    {
        $charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
        $sum = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($charset, $data[$i]);
            if ($pos !== false) {
                $sum += $pos;
            }
        }
        return $charset[$sum % 43];
    }
}
