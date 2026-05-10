<?php

declare(strict_types=1);

/**
 * Renders barcodes as raster images or SVG via horde/Image.
 *
 * Accepts any Horde_Image_Base backend (GD, Imagick, SVG) and draws
 * filled rectangles for each module or bar.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Renderer;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\ModuleMatrix;
use Horde_Image_Base;

final class ImageRenderer implements RendererInterface
{
    /**
     * @param array{tmpdir?: string} $context Context for Horde_Image creation
     * @param string $driver Backend driver class: 'Gd', 'Imagick', or 'Svg'
     */
    public function __construct(
        private readonly string $driver = 'Gd',
        private readonly array $context = [],
    ) {}

    public function renderMatrix(ModuleMatrix $matrix, RendererOptions $options): string
    {
        $scale = $options->scale;
        $quiet = $options->quietZone;
        $width = ($matrix->width() + $quiet * 2) * $scale;
        $height = ($matrix->height() + $quiet * 2) * $scale;

        if ($options->width !== null) {
            $width = $options->width;
            $scale = (int) floor($width / ($matrix->width() + $quiet * 2));
        }
        if ($options->height !== null) {
            $height = $options->height;
        }

        $image = $this->createImage($width, $height, $options->background);

        for ($row = 0; $row < $matrix->height(); $row++) {
            for ($col = 0; $col < $matrix->width(); $col++) {
                if ($matrix->isDark($row, $col)) {
                    $x = ($quiet + $col) * $scale;
                    $y = ($quiet + $row) * $scale;
                    /** @phpstan-ignore method.notFound */
                    $image->rectangle(
                        $x,
                        $y,
                        $scale,
                        $scale,
                        $options->foreground,
                        $options->foreground,
                    );
                }
            }
        }

        return $image->raw();
    }

    public function renderBars(BarPattern $bars, RendererOptions $options): string
    {
        $scale = $options->scale;
        $quiet = $options->quietZone;
        $barHeight = $options->height ?? (int) ($bars->height() * $scale * 20);
        $totalBarWidth = (int) ceil($bars->totalWidth() * $scale);
        $width = $totalBarWidth + $quiet * $scale * 2;
        $height = $barHeight + $quiet * $scale * 2;

        $image = $this->createImage($width, $height, $options->background);

        $x = $quiet * $scale;
        $y = $quiet * $scale;

        foreach ($bars->getBars() as $bar) {
            $barWidth = (int) round($bar->width * $scale);
            if ($barWidth < 1) {
                $barWidth = 1;
            }
            if ($bar->dark) {
                /** @phpstan-ignore method.notFound */
                $image->rectangle(
                    $x,
                    $y,
                    $barWidth,
                    $barHeight,
                    $options->foreground,
                    $options->foreground,
                );
            }
            $x += $barWidth;
        }

        return $image->raw();
    }

    private function createImage(int $width, int $height, string $background): Horde_Image_Base
    {
        $class = 'Horde_Image_' . $this->driver;
        $params = [
            'width' => $width,
            'height' => $height,
            'background' => $background,
            'type' => 'png',
        ];
        $context = $this->context;
        if (!isset($context['tmpdir'])) {
            $context['tmpdir'] = sys_get_temp_dir();
        }

        return new $class($params, $context);
    }
}
