<?php

declare(strict_types=1);

/**
 * Contract for barcode renderers.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Renderer;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\ModuleMatrix;

interface RendererInterface
{
    public function renderMatrix(ModuleMatrix $matrix, RendererOptions $options): string;

    public function renderBars(BarPattern $bars, RendererOptions $options): string;
}
