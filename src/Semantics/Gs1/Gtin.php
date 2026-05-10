<?php

declare(strict_types=1);

/**
 * GTIN (Global Trade Item Number) with check digit validation.
 *
 * Supports GTIN-8, GTIN-12, GTIN-13, and GTIN-14.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Gs1;

use Horde\Barcode\Encoder\EanUpcEncoder;
use Horde\Barcode\Exception\InvalidDataException;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class Gtin implements SemanticEncoderInterface, SemanticDecoderInterface
{
    private readonly string $gtin;

    public function __construct(string $gtin)
    {
        $gtin = preg_replace('/[^0-9]/', '', $gtin);
        if (!in_array(strlen($gtin), [8, 12, 13, 14], true)) {
            throw new InvalidDataException(
                sprintf('GTIN must be 8, 12, 13, or 14 digits, got %d', strlen($gtin))
            );
        }
        if (!$this->validateCheckDigit($gtin)) {
            throw new InvalidDataException('Invalid GTIN check digit');
        }
        $this->gtin = $gtin;
    }

    public static function withCheckDigit(string $digits): self
    {
        $digits = preg_replace('/[^0-9]/', '', $digits);
        $len = strlen($digits);
        if (!in_array($len, [7, 11, 12, 13], true)) {
            throw new InvalidDataException('GTIN base must be 7, 11, 12, or 13 digits');
        }
        $check = self::calculateCheckDigit($digits);
        return new self($digits . $check);
    }

    public function toPayload(): string
    {
        return $this->gtin;
    }

    public function recommendedEncoder(): string
    {
        return EanUpcEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        $payload = preg_replace('/[^0-9]/', '', $payload);
        $len = strlen($payload);
        return in_array($len, [8, 12, 13, 14], true);
    }

    public static function fromPayload(string $payload): static
    {
        return new static($payload);
    }

    public function getGtin(): string
    {
        return $this->gtin;
    }

    private function validateCheckDigit(string $gtin): bool
    {
        $base = substr($gtin, 0, -1);
        $expected = self::calculateCheckDigit($base);
        return $expected === $gtin[strlen($gtin) - 1];
    }

    private static function calculateCheckDigit(string $base): string
    {
        $sum = 0;
        $len = strlen($base);
        for ($i = 0; $i < $len; $i++) {
            $weight = (($len - $i) % 2 === 0) ? 3 : 1;
            $sum += (int) $base[$i] * $weight;
        }
        return (string) ((10 - ($sum % 10)) % 10);
    }
}
