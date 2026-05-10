<?php

declare(strict_types=1);

/**
 * GS1 Application Identifier registry and element string encoder/decoder.
 *
 * Parses and builds GS1 element strings using Application Identifiers (AIs).
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Gs1;

use Horde\Barcode\Encoder\Code128Encoder;
use Horde\Barcode\Exception\InvalidDataException;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class Gs1Barcode implements SemanticEncoderInterface, SemanticDecoderInterface
{
    private const FNC1 = "\x1D";

    /**
     * Common AIs: [ai => [title, fixedLength or 0 for variable, maxLength]]
     */
    private const AI_DEFINITIONS = [
        '00' => ['SSCC', 18, 18],
        '01' => ['GTIN', 14, 14],
        '02' => ['Content GTIN', 14, 14],
        '10' => ['Batch/Lot', 0, 20],
        '11' => ['Production Date', 6, 6],
        '13' => ['Packaging Date', 6, 6],
        '15' => ['Best Before', 6, 6],
        '17' => ['Expiry Date', 6, 6],
        '20' => ['Variant', 2, 2],
        '21' => ['Serial', 0, 20],
        '30' => ['Variable Count', 0, 8],
        '37' => ['Quantity', 0, 8],
        '240' => ['Additional ID', 0, 30],
        '241' => ['Customer Part No', 0, 30],
        '250' => ['Secondary Serial', 0, 30],
        '310' => ['Net Weight (kg)', 6, 6],
        '400' => ['Order Number', 0, 30],
        '410' => ['Ship To GLN', 13, 13],
        '411' => ['Bill To GLN', 13, 13],
        '412' => ['Purchase From GLN', 13, 13],
        '414' => ['Location GLN', 13, 13],
        '420' => ['Ship To Postal', 0, 20],
        '421' => ['Ship To Country+Postal', 0, 12],
        '8004' => ['GIAI', 0, 30],
        '8018' => ['GSRN', 18, 18],
    ];

    /**
     * @param array<string, string> $elements AI => value pairs
     */
    public function __construct(
        private readonly array $elements,
    ) {}

    public function toPayload(): string
    {
        $result = '';
        $keys = array_keys($this->elements);
        $count = count($keys);

        for ($i = 0; $i < $count; $i++) {
            $ai = $keys[$i];
            $value = $this->elements[$ai];
            $result .= $ai . $value;

            // Add FNC1 separator if variable-length and not the last element
            if ($i < $count - 1) {
                $def = self::AI_DEFINITIONS[$ai] ?? null;
                if ($def !== null && $def[1] === 0) {
                    $result .= self::FNC1;
                }
            }
        }

        return $result;
    }

    public function recommendedEncoder(): string
    {
        return Code128Encoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        // GS1 element strings start with a known AI
        $test = ltrim($payload, "\x1D");
        foreach (array_keys(self::AI_DEFINITIONS) as $ai) {
            if (str_starts_with($test, $ai)) {
                return true;
            }
        }
        return false;
    }

    public static function fromPayload(string $payload): static
    {
        $elements = [];
        $pos = 0;
        $len = strlen($payload);

        while ($pos < $len) {
            if ($payload[$pos] === self::FNC1) {
                $pos++;
                continue;
            }

            $matched = false;
            // Try matching AI (2, 3, or 4 digit prefixes)
            foreach ([4, 3, 2] as $aiLen) {
                if ($pos + $aiLen > $len) {
                    continue;
                }
                $ai = substr($payload, $pos, $aiLen);
                // Handle decimal indicator AIs (e.g., 310x where x is decimal places)
                $lookupAi = $ai;
                if (!isset(self::AI_DEFINITIONS[$ai]) && $aiLen >= 3) {
                    $lookupAi = substr($ai, 0, 3);
                }
                if (isset(self::AI_DEFINITIONS[$lookupAi]) || isset(self::AI_DEFINITIONS[$ai])) {
                    $def = self::AI_DEFINITIONS[$ai] ?? self::AI_DEFINITIONS[$lookupAi];
                    $pos += $aiLen;

                    if ($def[1] > 0) {
                        // Fixed length
                        $value = substr($payload, $pos, $def[1]);
                        $pos += $def[1];
                    } else {
                        // Variable length: read until FNC1 or end
                        $end = strpos($payload, self::FNC1, $pos);
                        if ($end === false) {
                            $end = $len;
                        }
                        $value = substr($payload, $pos, $end - $pos);
                        $pos = $end;
                    }
                    $elements[$ai] = $value;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                throw new InvalidDataException(
                    sprintf('Unknown GS1 Application Identifier at position %d', $pos)
                );
            }
        }

        return new static($elements);
    }

    /**
     * @return array<string, string>
     */
    public function getElements(): array
    {
        return $this->elements;
    }
}
