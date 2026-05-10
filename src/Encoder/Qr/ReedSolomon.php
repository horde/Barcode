<?php

declare(strict_types=1);

/**
 * Reed-Solomon error correction codec over GF(2^8) for QR codes.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder\Qr;

final class ReedSolomon
{
    /** @var list<int> Exponential table for GF(2^8) with polynomial 0x11D */
    private static array $exp = [];

    /** @var list<int> Log table for GF(2^8) */
    private static array $log = [];

    private static bool $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x >= 256) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$initialized = true;
    }

    private static function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /**
     * Generate error correction codewords for the given data.
     *
     * @param list<int> $data Data codewords (0-255 each)
     * @param int $ecCount Number of error correction codewords to generate
     * @return list<int> Error correction codewords
     */
    public static function encode(array $data, int $ecCount): array
    {
        self::init();

        $generator = self::buildGenerator($ecCount);
        $result = array_fill(0, $ecCount, 0);

        foreach ($data as $coef) {
            $factor = $coef ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $ecCount; $i++) {
                $result[$i] ^= self::multiply($generator[$i], $factor);
            }
        }

        return $result;
    }

    /**
     * Build the generator polynomial for the given EC codeword count.
     *
     * @return list<int> Polynomial coefficients
     */
    private static function buildGenerator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $newPoly = array_fill(0, count($poly) + 1, 0);
            for ($j = 0; $j < count($poly); $j++) {
                $newPoly[$j] ^= $poly[$j];
                $newPoly[$j + 1] ^= self::multiply($poly[$j], self::$exp[$i]);
            }
            $poly = $newPoly;
        }
        array_shift($poly);
        return $poly;
    }
}
