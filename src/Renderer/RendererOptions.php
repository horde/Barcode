<?php

declare(strict_types=1);

/**
 * Rendering options for barcode output.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Renderer;

final class RendererOptions
{
    public function __construct(
        public readonly int $scale = 3,
        public readonly string $foreground = '#000000',
        public readonly string $background = '#FFFFFF',
        public readonly int $quietZone = 4,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
    ) {}
}
