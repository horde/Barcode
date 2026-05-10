<?php

declare(strict_types=1);

/**
 * Immutable sequence of bars representing a linear (1D) barcode.
 *
 * Used as the output of linear encoders (Code 128, EAN/UPC, Code 39, ITF)
 * and the input to renderers.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Encoder;

final class BarPattern
{
    /**
     * @param list<Bar> $bars Sequence of bars from left to right
     * @param float $height Nominal height in abstract units
     */
    public function __construct(
        private readonly array $bars,
        private readonly float $height = 1.0,
    ) {}

    public function count(): int
    {
        return count($this->bars);
    }

    /**
     * @return list<Bar>
     */
    public function getBars(): array
    {
        return $this->bars;
    }

    public function height(): float
    {
        return $this->height;
    }

    public function totalWidth(): float
    {
        $width = 0.0;
        foreach ($this->bars as $bar) {
            $width += $bar->width;
        }
        return $width;
    }
}
