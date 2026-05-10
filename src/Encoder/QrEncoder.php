<?php

declare(strict_types=1);

/**
 * QR Code encoder implementing ISO/IEC 18004.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

use Horde\Barcode\Encoder\Qr\EncodingMode;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Encoder\Qr\MaskPattern;
use Horde\Barcode\Encoder\Qr\ReedSolomon;
use Horde\Barcode\Encoder\Qr\Version;
use Horde\Barcode\Exception\CapacityExceededException;
use Horde\Barcode\Exception\EncodingException;

final class QrEncoder implements TwoDimensionalEncoderInterface
{
    private const ALPHANUMERIC_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    public function __construct(
        private readonly ErrorCorrectionLevel $ecLevel = ErrorCorrectionLevel::M,
        private readonly ?int $minVersion = null,
    ) {}

    public function encode(string $data): ModuleMatrix
    {
        if ($data === '') {
            throw new EncodingException('Data must not be empty');
        }

        $mode = $this->selectMode($data);
        $version = $this->selectVersion($data, $mode);
        $dataBits = $this->encodeData($data, $mode, $version);
        $codewords = $this->buildCodewords($dataBits, $version);
        $matrix = $this->buildMatrix($codewords, $version);

        return new ModuleMatrix($matrix);
    }

    private function selectMode(string $data): EncodingMode
    {
        if (preg_match('/^\d+$/', $data)) {
            return EncodingMode::Numeric;
        }
        $len = strlen($data);
        $alphanumeric = true;
        for ($i = 0; $i < $len; $i++) {
            if (strpos(self::ALPHANUMERIC_CHARS, $data[$i]) === false) {
                $alphanumeric = false;
                break;
            }
        }
        if ($alphanumeric) {
            return EncodingMode::Alphanumeric;
        }
        return EncodingMode::Byte;
    }

    private function selectVersion(string $data, EncodingMode $mode): int
    {
        $dataLength = strlen($data);
        $start = max(1, $this->minVersion ?? 1);

        for ($v = $start; $v <= 40; $v++) {
            $capacity = Version::DATA_CODEWORDS[$v - 1][$this->ecLevel->value];
            $headerBits = 4 + $mode->characterCountBits($v);
            $dataBits = $this->dataBitsForMode($data, $mode);
            $totalBits = $headerBits + $dataBits;
            $totalCodewords = (int) ceil($totalBits / 8);

            if ($totalCodewords <= $capacity) {
                return $v;
            }
        }

        throw new CapacityExceededException(
            sprintf('Data too large for QR code (mode=%s, ecLevel=%s)', $mode->name, $this->ecLevel->name)
        );
    }

    private function dataBitsForMode(string $data, EncodingMode $mode): int
    {
        $len = strlen($data);
        return match ($mode) {
            EncodingMode::Numeric => (int) (10 * intdiv($len, 3)
                + match ($len % 3) {
                    1 => 4, 2 => 7, default => 0
                }),
            EncodingMode::Alphanumeric => 11 * intdiv($len, 2) + 6 * ($len % 2),
            EncodingMode::Byte => $len * 8,
            EncodingMode::Kanji => $len * 13,
        };
    }

    /**
     * @return list<int> Bit array (0 or 1 values)
     */
    private function encodeData(string $data, EncodingMode $mode, int $version): array
    {
        $bits = [];

        // Mode indicator (4 bits)
        $this->appendBits($bits, $mode->value, 4);

        // Character count indicator
        $ccBits = $mode->characterCountBits($version);
        $this->appendBits($bits, strlen($data), $ccBits);

        // Data encoding
        match ($mode) {
            EncodingMode::Numeric => $this->encodeNumeric($bits, $data),
            EncodingMode::Alphanumeric => $this->encodeAlphanumeric($bits, $data),
            EncodingMode::Byte => $this->encodeByte($bits, $data),
            EncodingMode::Kanji => throw new EncodingException('Kanji mode not yet implemented'),
        };

        return $bits;
    }

    /**
     * @param list<int> &$bits
     */
    private function encodeNumeric(array &$bits, string $data): void
    {
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 3) {
            $group = substr($data, $i, 3);
            $value = (int) $group;
            $numBits = match (strlen($group)) {
                3 => 10,
                2 => 7,
                1 => 4,
                default => 4,
            };
            $this->appendBits($bits, $value, $numBits);
        }
    }

    /**
     * @param list<int> &$bits
     */
    private function encodeAlphanumeric(array &$bits, string $data): void
    {
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 2) {
            if ($i + 1 < $len) {
                $val = $this->alphanumericValue($data[$i]) * 45 + $this->alphanumericValue($data[$i + 1]);
                $this->appendBits($bits, $val, 11);
            } else {
                $this->appendBits($bits, $this->alphanumericValue($data[$i]), 6);
            }
        }
    }

    private function alphanumericValue(string $char): int
    {
        $pos = strpos(self::ALPHANUMERIC_CHARS, $char);
        if ($pos === false) {
            throw new EncodingException(sprintf('Invalid alphanumeric character: %s', $char));
        }
        return $pos;
    }

    /**
     * @param list<int> &$bits
     */
    private function encodeByte(array &$bits, string $data): void
    {
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $this->appendBits($bits, ord($data[$i]), 8);
        }
    }

    /**
     * @param list<int> &$bits
     */
    private function appendBits(array &$bits, int $value, int $numBits): void
    {
        for ($i = $numBits - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * @param list<int> $dataBits
     * @return list<int> Final interleaved codewords
     */
    private function buildCodewords(array $dataBits, int $version): array
    {
        $dataCapacity = Version::DATA_CODEWORDS[$version - 1][$this->ecLevel->value];
        $totalBits = $dataCapacity * 8;

        // Add terminator
        $terminatorLength = min(4, $totalBits - count($dataBits));
        for ($i = 0; $i < $terminatorLength; $i++) {
            $dataBits[] = 0;
        }

        // Pad to byte boundary
        while (count($dataBits) % 8 !== 0) {
            $dataBits[] = 0;
        }

        // Pad with alternating bytes 0xEC, 0x11
        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        while (count($dataBits) < $totalBits) {
            $this->appendBits($dataBits, $padBytes[$padIndex], 8);
            $padIndex = 1 - $padIndex;
        }

        // Convert to codewords
        $dataCodewords = [];
        for ($i = 0; $i < count($dataBits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $dataBits[$i + $j];
            }
            $dataCodewords[] = $byte;
        }

        // Split into blocks and generate EC
        $ecPerBlock = Version::EC_CODEWORDS_PER_BLOCK[$version - 1][$this->ecLevel->value];
        [$group1Count, $group2Count] = Version::EC_BLOCKS[$version - 1][$this->ecLevel->value];
        $totalBlocks = $group1Count + $group2Count;

        $group1Size = intdiv($dataCapacity, $totalBlocks);
        $group2Size = $group1Size + 1;

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        for ($b = 0; $b < $group1Count; $b++) {
            $block = array_slice($dataCodewords, $offset, $group1Size);
            $dataBlocks[] = $block;
            $ecBlocks[] = ReedSolomon::encode($block, $ecPerBlock);
            $offset += $group1Size;
        }
        for ($b = 0; $b < $group2Count; $b++) {
            $block = array_slice($dataCodewords, $offset, $group2Size);
            $dataBlocks[] = $block;
            $ecBlocks[] = ReedSolomon::encode($block, $ecPerBlock);
            $offset += $group2Size;
        }

        // Interleave data codewords
        $result = [];
        $maxDataLen = max($group1Size, $group2Size);
        for ($i = 0; $i < $maxDataLen; $i++) {
            for ($b = 0; $b < $totalBlocks; $b++) {
                if ($i < count($dataBlocks[$b])) {
                    $result[] = $dataBlocks[$b][$i];
                }
            }
        }

        // Interleave EC codewords
        for ($i = 0; $i < $ecPerBlock; $i++) {
            for ($b = 0; $b < $totalBlocks; $b++) {
                $result[] = $ecBlocks[$b][$i];
            }
        }

        return $result;
    }

    /**
     * @param list<int> $codewords
     * @return list<list<bool>>
     */
    private function buildMatrix(array $codewords, int $version): array
    {
        $size = Version::size($version);

        // null = not yet placed, true/false = module value
        /** @var list<list<bool|null>> $matrix */
        $matrix = array_fill(0, $size, array_fill(0, $size, null));

        // Track function pattern positions
        /** @var list<list<bool>> $reserved */
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // Place finder patterns
        $this->placeFinderPattern($matrix, $reserved, 0, 0);
        $this->placeFinderPattern($matrix, $reserved, 0, $size - 7);
        $this->placeFinderPattern($matrix, $reserved, $size - 7, 0);

        // Place separators
        for ($i = 0; $i < 8; $i++) {
            // Top-left
            $this->setReserved($matrix, $reserved, $size, 7, $i, false);
            $this->setReserved($matrix, $reserved, $size, $i, 7, false);
            // Top-right
            $this->setReserved($matrix, $reserved, $size, 7, $size - 8 + $i, false);
            $this->setReserved($matrix, $reserved, $size, $i, $size - 8, false);
            // Bottom-left
            $this->setReserved($matrix, $reserved, $size, $size - 8, $i, false);
            $this->setReserved($matrix, $reserved, $size, $size - 8 + $i, 7, false);
        }

        // Place alignment patterns
        if ($version >= 2) {
            $positions = Version::ALIGNMENT_PATTERNS[$version - 2];
            foreach ($positions as $row) {
                foreach ($positions as $col) {
                    // Skip if overlapping with finder patterns
                    if ($reserved[$row][$col]) {
                        continue;
                    }
                    $this->placeAlignmentPattern($matrix, $reserved, $row, $col);
                }
            }
        }

        // Place timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $val = $i % 2 === 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $val;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $val;
                $reserved[$i][6] = true;
            }
        }

        // Dark module
        $matrix[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;

        // Reserve format info areas
        for ($i = 0; $i < 9; $i++) {
            if ($i < $size) {
                $reserved[8][$i] = true;
            }
            if ($i < $size) {
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 8 + $i] = true;
        }
        for ($i = 0; $i < 7; $i++) {
            $reserved[$size - 7 + $i][8] = true;
        }

        // Reserve version info areas (versions 7+)
        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserved[$i][$size - 11 + $j] = true;
                    $reserved[$size - 11 + $j][$i] = true;
                }
            }
        }

        // Place data bits
        $this->placeDataBits($matrix, $reserved, $codewords, $size);

        // Try all mask patterns
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $this->applyMask($matrix, $reserved, $mask, $size);
            $this->placeFormatInfo($candidate, $size, $mask);
            if ($version >= 7) {
                $this->placeVersionInfo($candidate, $size, $version);
            }
            $penalty = MaskPattern::penalty($candidate);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        return $bestMatrix;
    }

    /**
     * @param array<int, array<int, bool|null>> &$matrix
     * @param array<int, array<int, bool>> &$reserved
     */
    private function placeFinderPattern(array &$matrix, array &$reserved, int $row, int $col): void
    {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $dark = ($r === 0 || $r === 6 || $c === 0 || $c === 6
                    || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $matrix[$row + $r][$col + $c] = $dark;
                $reserved[$row + $r][$col + $c] = true;
            }
        }
    }

    /**
     * @param array<int, array<int, bool|null>> &$matrix
     * @param array<int, array<int, bool>> &$reserved
     */
    private function placeAlignmentPattern(array &$matrix, array &$reserved, int $centerRow, int $centerCol): void
    {
        for ($r = -2; $r <= 2; $r++) {
            for ($c = -2; $c <= 2; $c++) {
                $dark = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                $matrix[$centerRow + $r][$centerCol + $c] = $dark;
                $reserved[$centerRow + $r][$centerCol + $c] = true;
            }
        }
    }

    /**
     * @param array<int, array<int, bool|null>> &$matrix
     * @param array<int, array<int, bool>> &$reserved
     */
    private function setReserved(array &$matrix, array &$reserved, int $size, int $row, int $col, bool $value): void
    {
        if ($row >= 0 && $row < $size && $col >= 0 && $col < $size) {
            if ($matrix[$row][$col] === null) {
                $matrix[$row][$col] = $value;
            }
            $reserved[$row][$col] = true;
        }
    }

    /**
     * @param array<int, array<int, bool|null>> &$matrix
     * @param array<int, array<int, bool>> &$reserved
     * @param list<int> $codewords
     */
    private function placeDataBits(array &$matrix, array &$reserved, array $codewords, int $size): void
    {
        $bitIndex = 0;
        $totalBits = count($codewords) * 8;

        // Traverse in 2-column zigzag pattern right-to-left
        $col = $size - 1;
        while ($col >= 0) {
            // Skip timing pattern column
            if ($col === 6) {
                $col--;
                continue;
            }

            for ($row = 0; $row < $size; $row++) {
                for ($c = 0; $c < 2; $c++) {
                    $actualCol = $col - $c;
                    // Determine direction: upward for even passes, downward for odd
                    $isUpward = intdiv($size - 1 - $col, 2) % 2 === 0;
                    $actualRow = $isUpward ? $size - 1 - $row : $row;

                    if ($actualCol < 0 || $reserved[$actualRow][$actualCol]) {
                        continue;
                    }

                    if ($bitIndex < $totalBits) {
                        $codewordIndex = intdiv($bitIndex, 8);
                        $bitOffset = 7 - ($bitIndex % 8);
                        $matrix[$actualRow][$actualCol] = (($codewords[$codewordIndex] >> $bitOffset) & 1) === 1;
                        $bitIndex++;
                    } else {
                        $matrix[$actualRow][$actualCol] = false;
                    }
                }
            }
            $col -= 2;
        }
    }

    /**
     * @param array<int, array<int, bool|null>> $matrix
     * @param array<int, array<int, bool>> $reserved
     * @return list<list<bool>>
     */
    private function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        $result = [];
        for ($row = 0; $row < $size; $row++) {
            $resultRow = [];
            for ($col = 0; $col < $size; $col++) {
                $value = $matrix[$row][$col] ?? false;
                if (!$reserved[$row][$col] && MaskPattern::evaluate($mask, $row, $col)) {
                    $value = !$value;
                }
                $resultRow[] = $value;
            }
            $result[] = $resultRow;
        }
        return $result;
    }

    /**
     * @param array<int, array<int, bool>> &$matrix
     */
    private function placeFormatInfo(array &$matrix, int $size, int $mask): void
    {
        $data = ($this->ecLevel->formatBits() << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        // Place format bits around finder patterns
        for ($i = 0; $i < 6; $i++) {
            $matrix[8][$i] = (($bits >> $i) & 1) === 1;
        }
        $matrix[8][7] = (($bits >> 6) & 1) === 1;
        $matrix[8][8] = (($bits >> 7) & 1) === 1;
        $matrix[7][8] = (($bits >> 8) & 1) === 1;
        for ($i = 9; $i < 15; $i++) {
            $matrix[14 - $i][8] = (($bits >> $i) & 1) === 1;
        }

        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 8 + $i] = (($bits >> $i) & 1) === 1;
        }
        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = (($bits >> (8 + $i)) & 1) === 1;
        }
    }

    /**
     * @param array<int, array<int, bool>> &$matrix
     */
    private function placeVersionInfo(array &$matrix, int $size, int $version): void
    {
        $bits = Version::VERSION_INFO[$version - 7];
        for ($i = 0; $i < 18; $i++) {
            $bit = (($bits >> $i) & 1) === 1;
            $row = intdiv($i, 3);
            $col = $size - 11 + ($i % 3);
            $matrix[$row][$col] = $bit;
            $matrix[$col][$row] = $bit;
        }
    }
}
