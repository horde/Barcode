<?php

declare(strict_types=1);

/**
 * Renders barcodes into a PDF document via horde/Pdf.
 *
 * Draws filled rectangles using PdfWriter's cell method. The caller
 * provides a PdfWriter instance with an active page; the renderer
 * draws the barcode at the specified position.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Renderer;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Pdf\Color;
use Horde\Pdf\PdfWriter;

final class PdfRenderer implements RendererInterface
{
    /**
     * @param PdfWriter $pdf An open PdfWriter instance with an active page
     * @param float $x X position in current units (left edge of barcode)
     * @param float $y Y position in current units (top edge of barcode)
     * @param float $moduleSize Size of one module in current units (mm default)
     */
    public function __construct(
        private readonly PdfWriter $pdf,
        private readonly float $x = 10.0,
        private readonly float $y = 10.0,
        private readonly float $moduleSize = 0.5,
    ) {}

    public function renderMatrix(ModuleMatrix $matrix, RendererOptions $options): string
    {
        $quiet = $options->quietZone;
        $size = $this->moduleSize;

        $fgColor = $this->parseColor($options->foreground);
        $bgColor = $this->parseColor($options->background);

        // Draw background
        $totalWidth = ($matrix->width() + $quiet * 2) * $size;
        $totalHeight = ($matrix->height() + $quiet * 2) * $size;
        $this->pdf->setFillColor($bgColor);
        $this->pdf->setXY($this->x, $this->y);
        $this->pdf->cell($totalWidth, $totalHeight, '', 0, 0, '', true);

        // Draw dark modules
        $this->pdf->setFillColor($fgColor);
        for ($row = 0; $row < $matrix->height(); $row++) {
            for ($col = 0; $col < $matrix->width(); $col++) {
                if ($matrix->isDark($row, $col)) {
                    $cellX = $this->x + ($quiet + $col) * $size;
                    $cellY = $this->y + ($quiet + $row) * $size;
                    $this->pdf->setXY($cellX, $cellY);
                    $this->pdf->cell($size, $size, '', 0, 0, '', true);
                }
            }
        }

        return '';
    }

    public function renderBars(BarPattern $bars, RendererOptions $options): string
    {
        $quiet = $options->quietZone;
        $size = $this->moduleSize;
        $barHeight = ($options->height !== null)
            ? $options->height * $size
            : $bars->height() * $size * 20;

        $fgColor = $this->parseColor($options->foreground);
        $bgColor = $this->parseColor($options->background);

        // Draw background
        $totalWidth = ($bars->totalWidth() + $quiet * 2) * $size;
        $totalHeight = $barHeight + $quiet * 2 * $size;
        $this->pdf->setFillColor($bgColor);
        $this->pdf->setXY($this->x, $this->y);
        $this->pdf->cell($totalWidth, $totalHeight, '', 0, 0, '', true);

        // Draw bars
        $x = $this->x + $quiet * $size;
        $y = $this->y + $quiet * $size;

        foreach ($bars->getBars() as $bar) {
            $barWidth = $bar->width * $size;
            if ($bar->dark) {
                $this->pdf->setFillColor($fgColor);
                $this->pdf->setXY($x, $y);
                $this->pdf->cell($barWidth, $barHeight, '', 0, 0, '', true);
            }
            $x += $barWidth;
        }

        return '';
    }

    private function parseColor(string $hex): Color
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2)) / 255.0;
        $g = hexdec(substr($hex, 2, 2)) / 255.0;
        $b = hexdec(substr($hex, 4, 2)) / 255.0;
        return Color::rgb($r, $g, $b);
    }
}
