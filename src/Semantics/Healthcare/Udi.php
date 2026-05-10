<?php

declare(strict_types=1);

/**
 * Unique Device Identification (UDI) barcode payload.
 *
 * Implements FDA 21 CFR Part 830 / EU MDR 2017/745 device identification.
 * UDI consists of a Device Identifier (DI) and Production Identifiers (PI).
 * Typically encoded as GS1 or HIBCC barcode.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Healthcare;

use Horde\Barcode\Encoder\DataMatrixEncoder;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class Udi implements SemanticEncoderInterface, SemanticDecoderInterface
{
    private const GS = "\x1D";

    /**
     * @param string $deviceIdentifier GTIN or HIBCC primary code (DI)
     * @param string $lotNumber Manufacturing lot/batch (PI)
     * @param string $serialNumber Device serial number (PI)
     * @param string $expirationDate YYMMDD format (PI)
     * @param string $manufacturingDate YYMMDD format (PI)
     * @param string $issuingAgency 'GS1' or 'HIBCC' or 'ICCBBA'
     */
    public function __construct(
        private readonly string $deviceIdentifier,
        private readonly string $lotNumber = '',
        private readonly string $serialNumber = '',
        private readonly string $expirationDate = '',
        private readonly string $manufacturingDate = '',
        private readonly string $issuingAgency = 'GS1',
    ) {}

    public function toPayload(): string
    {
        if (strtoupper($this->issuingAgency) === 'HIBCC') {
            return $this->toHibccPayload();
        }

        return $this->toGs1Payload();
    }

    public function recommendedEncoder(): string
    {
        return DataMatrixEncoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        if (str_starts_with($payload, ']d2')) {
            return true;
        }
        if (str_starts_with($payload, '+')) {
            return true;
        }
        if (preg_match('/^01\d{14}/', $payload)) {
            return true;
        }
        return false;
    }

    public static function fromPayload(string $payload): static
    {
        $payload = ltrim($payload, ']d2');

        if (str_starts_with($payload, '+')) {
            return self::fromHibccPayload($payload);
        }

        return self::fromGs1Payload($payload);
    }

    private function toGs1Payload(): string
    {
        $result = '01' . str_pad($this->deviceIdentifier, 14, '0', STR_PAD_LEFT);

        if ($this->expirationDate !== '') {
            $result .= '17' . $this->expirationDate;
        }
        if ($this->manufacturingDate !== '') {
            $result .= '11' . $this->manufacturingDate;
        }
        if ($this->lotNumber !== '') {
            $result .= '10' . $this->lotNumber . self::GS;
        }
        if ($this->serialNumber !== '') {
            $result .= '21' . $this->serialNumber . self::GS;
        }

        return rtrim($result, self::GS);
    }

    private function toHibccPayload(): string
    {
        $primary = '+' . $this->deviceIdentifier;
        $secondary = '+$$';
        if ($this->expirationDate !== '') {
            $secondary .= $this->expirationDate;
        }
        if ($this->lotNumber !== '') {
            $secondary .= '/' . $this->lotNumber;
        }
        if ($this->serialNumber !== '') {
            $secondary .= '+S' . $this->serialNumber;
        }
        return $primary . '/' . $secondary;
    }

    private static function fromGs1Payload(string $payload): static
    {
        $di = '';
        $lot = '';
        $serial = '';
        $expiry = '';
        $mfg = '';
        $pos = 0;
        $len = strlen($payload);

        while ($pos < $len) {
            $ai = substr($payload, $pos, 2);
            $pos += 2;

            switch ($ai) {
                case '01':
                    $di = substr($payload, $pos, 14);
                    $pos += 14;
                    break;
                case '17':
                    $expiry = substr($payload, $pos, 6);
                    $pos += 6;
                    break;
                case '11':
                    $mfg = substr($payload, $pos, 6);
                    $pos += 6;
                    break;
                case '10':
                    $end = strpos($payload, self::GS, $pos);
                    $lot = $end === false
                        ? substr($payload, $pos)
                        : substr($payload, $pos, $end - $pos);
                    $pos = $end === false ? $len : $end + 1;
                    break;
                case '21':
                    $end = strpos($payload, self::GS, $pos);
                    $serial = $end === false
                        ? substr($payload, $pos)
                        : substr($payload, $pos, $end - $pos);
                    $pos = $end === false ? $len : $end + 1;
                    break;
                default:
                    $pos = $len;
                    break;
            }
        }

        return new static(
            deviceIdentifier: ltrim($di, '0') ?: $di,
            lotNumber: $lot,
            serialNumber: $serial,
            expirationDate: $expiry,
            manufacturingDate: $mfg,
            issuingAgency: 'GS1',
        );
    }

    private static function fromHibccPayload(string $payload): static
    {
        $parts = explode('/', $payload, 3);
        $di = ltrim($parts[0], '+');
        $lot = '';
        $serial = '';
        $expiry = '';

        if (isset($parts[1])) {
            $secondary = ltrim($parts[1], '+$');
            if (preg_match('/^(\d{6})/', $secondary, $m)) {
                $expiry = $m[1];
                $secondary = substr($secondary, 6);
            }
            if (str_starts_with($secondary, '/')) {
                $secondary = substr($secondary, 1);
            }
            $serialPos = strpos($secondary, '+S');
            if ($serialPos !== false) {
                $lot = substr($secondary, 0, $serialPos);
                $serial = substr($secondary, $serialPos + 2);
            } else {
                $lot = $secondary;
            }
        }

        return new static(
            deviceIdentifier: $di,
            lotNumber: $lot,
            serialNumber: $serial,
            expirationDate: $expiry,
            issuingAgency: 'HIBCC',
        );
    }
}
