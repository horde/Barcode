<?php

declare(strict_types=1);

/**
 * Immutable 2D module grid representing a barcode symbol.
 *
 * Each cell is either dark (true) or light (false). Used as the output
 * of 2D encoders (QR, Data Matrix, Aztec, PDF417) and the input to renderers.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

final class ModuleMatrix
{
    private readonly int $width;
    private readonly int $height;

    /**
     * @param list<list<bool>> $modules Rows of columns, true = dark module
     */
    public function __construct(
        private readonly array $modules,
    ) {
        $this->height = count($modules);
        $this->width = $this->height > 0 ? count($modules[0]) : 0;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function isDark(int $row, int $col): bool
    {
        return $this->modules[$row][$col];
    }

    /**
     * @return list<list<bool>>
     */
    public function toArray(): array
    {
        return $this->modules;
    }
}
