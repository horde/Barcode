<?php

declare(strict_types=1);

/**
 * Data Matrix barcode encoder implementing ISO/IEC 16022 (ECC200).
 *
 * Supports ASCII encoding mode (default) which handles all byte values.
 * Automatically selects the smallest symbol size that fits the data.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

use Horde\Barcode\Exception\CapacityExceededException;
use Horde\Barcode\Exception\EncodingException;

final class DataMatrixEncoder implements TwoDimensionalEncoderInterface
{
    /**
     * Symbol sizes: [rows, cols, dataRegionRows, dataRegionCols, dataCodewords, ecCodewords, blocks]
     * @var list<list<int>>
     */
    private const SIZES = [
        [10, 10, 8, 8, 3, 5, 1],
        [12, 12, 10, 10, 5, 7, 1],
        [14, 14, 12, 12, 8, 10, 1],
        [16, 16, 14, 14, 12, 12, 1],
        [18, 18, 16, 16, 18, 14, 1],
        [20, 20, 18, 18, 22, 18, 1],
        [22, 22, 20, 20, 30, 20, 1],
        [24, 24, 22, 22, 36, 24, 1],
        [26, 26, 24, 24, 44, 28, 1],
        [32, 32, 14, 14, 62, 36, 1],
        [36, 36, 16, 16, 86, 42, 1],
        [40, 40, 18, 18, 114, 48, 1],
        [44, 44, 20, 20, 144, 56, 1],
        [48, 48, 22, 22, 174, 68, 1],
        [52, 52, 24, 24, 204, 84, 2],
        [64, 64, 14, 14, 280, 112, 2],
        [72, 72, 16, 16, 368, 144, 4],
        [80, 80, 18, 18, 456, 192, 4],
        [88, 88, 20, 20, 576, 224, 4],
        [96, 96, 22, 22, 696, 272, 4],
        [104, 104, 24, 24, 816, 336, 6],
        [120, 120, 18, 18, 1050, 408, 6],
        [132, 132, 20, 20, 1304, 496, 8],
        [144, 144, 22, 22, 1558, 620, 10],
    ];

    /**
     * Reed-Solomon log/antilog tables for GF(301).
     */
    private static array $log = [];
    private static array $alog = [];
    private static bool $initialized = false;

    public function encode(string $data): ModuleMatrix
    {
        if ($data === '') {
            throw new EncodingException('Data must not be empty for Data Matrix');
        }

        self::initGalois();

        $codewords = $this->encodeAscii($data);
        $sizeIndex = $this->selectSize(count($codewords));
        $size = self::SIZES[$sizeIndex];

        $dataCapacity = $size[4];
        $ecCount = $size[5];
        $blocks = $size[6];

        // Pad codewords
        while (count($codewords) < $dataCapacity) {
            $pad = 129 + ((count($codewords) + 1) * 149 % 253 + 1) % 254;
            if ($pad > 254) {
                $pad -= 254;
            }
            $codewords[] = $pad;
        }

        // Generate error correction
        $ecPerBlock = intdiv($ecCount, $blocks);
        $dataPerBlock = intdiv($dataCapacity, $blocks);

        $allCodewords = [];
        for ($b = 0; $b < $blocks; $b++) {
            $blockData = array_slice($codewords, $b * $dataPerBlock, $dataPerBlock);
            $ec = $this->reedSolomonEncode($blockData, $ecPerBlock);
            $allCodewords = array_merge($allCodewords, $blockData, $ec);
        }

        // Build the module matrix
        $matrix = $this->buildMatrix($allCodewords, $size);

        return new ModuleMatrix($matrix);
    }

    /**
     * @return list<int> ASCII-encoded codewords
     */
    private function encodeAscii(string $data): array
    {
        $codewords = [];
        $len = strlen($data);
        $i = 0;

        while ($i < $len) {
            $c = ord($data[$i]);

            // Digit pair optimization
            if ($i + 1 < $len && $c >= 48 && $c <= 57) {
                $c2 = ord($data[$i + 1]);
                if ($c2 >= 48 && $c2 <= 57) {
                    $codewords[] = (($c - 48) * 10 + ($c2 - 48)) + 130;
                    $i += 2;
                    continue;
                }
            }

            if ($c <= 127) {
                $codewords[] = $c + 1;
            } else {
                // Extended ASCII
                $codewords[] = 235;
                $codewords[] = $c - 127;
            }
            $i++;
        }

        return $codewords;
    }

    private function selectSize(int $dataLength): int
    {
        foreach (self::SIZES as $index => $size) {
            if ($dataLength <= $size[4]) {
                return $index;
            }
        }
        throw new CapacityExceededException('Data too large for any Data Matrix symbol size');
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private function reedSolomonEncode(array $data, int $ecCount): array
    {
        $poly = $this->buildGenerator($ecCount);
        $ec = array_fill(0, $ecCount, 0);

        foreach ($data as $coef) {
            $k = $ec[0] ^ $coef;
            for ($i = 0; $i < $ecCount - 1; $i++) {
                $ec[$i] = $ec[$i + 1] ^ $this->gfMultiply($k, $poly[$i]);
            }
            $ec[$ecCount - 1] = $this->gfMultiply($k, $poly[$ecCount - 1]);
        }

        return $ec;
    }

    /**
     * @return list<int>
     */
    private function buildGenerator(int $n): array
    {
        $poly = array_fill(0, $n, 0);
        $poly[$n - 1] = 1;

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $poly[$j] = $this->gfMultiply($poly[$j], self::$alog[$i]);
                if ($j > 0) {
                    $poly[$j] ^= $poly[$j - 1];
                }
            }
        }

        return $poly;
    }

    private function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$alog[(self::$log[$a] + self::$log[$b]) % 255];
    }

    private static function initGalois(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$log = array_fill(0, 256, 0);
        self::$alog = array_fill(0, 256, 0);

        $p = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$alog[$i] = $p;
            self::$log[$p] = $i;
            $p *= 2;
            if ($p >= 256) {
                $p ^= 301;
            }
        }
        self::$alog[255] = 1;
        self::$initialized = true;
    }

    /**
     * @param list<int> $codewords
     * @param list<int> $size
     * @return list<list<bool>>
     */
    private function buildMatrix(array $codewords, array $size): array
    {
        $rows = $size[0];
        $cols = $size[1];
        $dataRows = $size[2];
        $dataCols = $size[3];

        // Initialize matrix
        $matrix = array_fill(0, $rows, array_fill(0, $cols, false));

        // Place finder pattern (solid edges + timing)
        $numRegionsH = intdiv($rows, $dataRows + 2);
        $numRegionsW = intdiv($cols, $dataCols + 2);

        for ($rr = 0; $rr < $numRegionsH; $rr++) {
            for ($rc = 0; $rc < $numRegionsW; $rc++) {
                $baseRow = $rr * ($dataRows + 2);
                $baseCol = $rc * ($dataCols + 2);

                // Right timing (alternating) — row 0 = dark
                for ($r = 0; $r < $dataRows + 2; $r++) {
                    $matrix[$baseRow + $r][$baseCol + $dataCols + 1] = ($r % 2 === 0);
                }
                // Top timing (alternating) — col 0 = dark; applied after right timing so top-right corner is correct
                for ($c = 0; $c < $dataCols + 2; $c++) {
                    $matrix[$baseRow][$baseCol + $c] = ($c % 2 === 0);
                }
                // Bottom solid line (L-shape bottom) — always dark
                for ($c = 0; $c < $dataCols + 2; $c++) {
                    $matrix[$baseRow + $dataRows + 1][$baseCol + $c] = true;
                }
                // Left solid line (L-shape left) — always dark
                for ($r = 0; $r < $dataRows + 2; $r++) {
                    $matrix[$baseRow + $r][$baseCol] = true;
                }
            }
        }

        // Place data using the standard Data Matrix placement algorithm
        $this->placeData($matrix, $codewords, $size);

        return $matrix;
    }

    /**
     * Place codewords into the data region using the standard diagonal path.
     *
     * @param array<int, array<int, bool>> &$matrix
     * @param list<int> $codewords
     * @param list<int> $size
     */
    private function placeData(array &$matrix, array $codewords, array $size): void
    {
        $rows = $size[0];
        $cols = $size[1];
        $dataRows = $size[2];
        $dataCols = $size[3];

        $numRegionsH = intdiv($rows, $dataRows + 2);
        $numRegionsW = intdiv($cols, $dataCols + 2);
        $mappingRows = $dataRows * $numRegionsH;
        $mappingCols = $dataCols * $numRegionsW;

        // Create a mapping matrix for placement (without alignment patterns)
        $placed = array_fill(0, $mappingRows, array_fill(0, $mappingCols, false));
        $values = array_fill(0, $mappingRows, array_fill(0, $mappingCols, false));

        $row = 4;
        $col = 0;
        $bitIdx = 0;
        $totalBits = count($codewords) * 8;

        while ($row < $mappingRows || $col < $mappingCols) {
            // Going up-right
            while ($row >= 0 && $col < $mappingCols) {
                if ($row < $mappingRows && !$placed[$row][$col]) {
                    $this->placeModule($placed, $values, $row, $col, $codewords, $bitIdx, $mappingRows, $mappingCols);
                    $bitIdx += 8;
                }
                $row -= 2;
                $col += 2;
            }
            $row += 1;
            $col += 3;

            // Going down-left
            while ($row < $mappingRows && $col >= 0) {
                if ($row >= 0 && $col < $mappingCols && !$placed[$row][$col]) {
                    $this->placeModule($placed, $values, $row, $col, $codewords, $bitIdx, $mappingRows, $mappingCols);
                    $bitIdx += 8;
                }
                $row += 2;
                $col -= 2;
            }
            $row += 3;
            $col += 1;

            if ($row >= $mappingRows && $col >= $mappingCols) {
                break;
            }
        }

        // Copy from mapping matrix to actual matrix (inserting alignment patterns)
        for ($mr = 0; $mr < $mappingRows; $mr++) {
            for ($mc = 0; $mc < $mappingCols; $mc++) {
                $regionRow = intdiv($mr, $dataRows);
                $regionCol = intdiv($mc, $dataCols);
                $actualRow = $mr + ($regionRow * 2) + 1;
                $actualCol = $mc + ($regionCol * 2) + 1;
                $matrix[$actualRow][$actualCol] = $values[$mr][$mc];
            }
        }
    }

    /**
     * @param array<int, array<int, bool>> &$placed
     * @param array<int, array<int, bool>> &$values
     * @param list<int> $codewords
     */
    private function placeModule(
        array &$placed,
        array &$values,
        int $row,
        int $col,
        array $codewords,
        int $bitIdx,
        int $numRows,
        int $numCols,
    ): void {
        // The 8 bits of a codeword are placed in the Utah shape
        $codewordIdx = intdiv($bitIdx, 8);
        if ($codewordIdx >= count($codewords)) {
            if ($row >= 0 && $row < $numRows && $col >= 0 && $col < $numCols) {
                $placed[$row][$col] = true;
            }
            return;
        }

        $codeword = $codewords[$codewordIdx];
        $offsets = [
            [-2, -2], [-2, -1], [-1, -2], [-1, -1],
            [-1, 0], [0, -2], [0, -1], [0, 0],
        ];

        for ($bit = 0; $bit < 8; $bit++) {
            $dr = $row + $offsets[$bit][0];
            $dc = $col + $offsets[$bit][1];

            // Wrap around for corner cases
            if ($dr < 0) {
                $dr += $numRows;
                $dc += 4 - (($numRows + 4) % 8);
            }
            if ($dc < 0) {
                $dc += $numCols;
                $dr += 4 - (($numCols + 4) % 8);
            }

            if ($dr >= 0 && $dr < $numRows && $dc >= 0 && $dc < $numCols && !$placed[$dr][$dc]) {
                $placed[$dr][$dc] = true;
                $values[$dr][$dc] = (($codeword >> (7 - $bit)) & 1) === 1;
            }
        }
    }
}
