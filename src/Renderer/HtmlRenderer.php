<?php

declare(strict_types=1);

/**
 * Renders barcodes as HTML tables with inline CSS.
 *
 * No external dependencies required. Each module becomes a table cell
 * with background-color styling.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Renderer;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\ModuleMatrix;

final class HtmlRenderer implements RendererInterface
{
    public function renderMatrix(ModuleMatrix $matrix, RendererOptions $options): string
    {
        $scale = $options->scale;
        $fg = htmlspecialchars($options->foreground, ENT_QUOTES);
        $bg = htmlspecialchars($options->background, ENT_QUOTES);
        $quiet = $options->quietZone;

        $cellStyle = sprintf('width:%dpx;height:%dpx;', $scale, $scale);
        $darkCell = sprintf('<td style="%sbackground:%s"></td>', $cellStyle, $fg);
        $lightCell = sprintf('<td style="%sbackground:%s"></td>', $cellStyle, $bg);

        $totalWidth = ($matrix->width() + $quiet * 2) * $scale;

        $html = sprintf(
            '<table style="border-collapse:collapse;border-spacing:0;margin:0;padding:0;line-height:0;width:%dpx">',
            $totalWidth,
        );

        // Top quiet zone
        $quietRow = '<tr>' . str_repeat($lightCell, $matrix->width() + $quiet * 2) . '</tr>';
        $html .= str_repeat($quietRow, $quiet);

        // Data rows
        for ($row = 0; $row < $matrix->height(); $row++) {
            $html .= '<tr>';
            $html .= str_repeat($lightCell, $quiet);
            for ($col = 0; $col < $matrix->width(); $col++) {
                $html .= $matrix->isDark($row, $col) ? $darkCell : $lightCell;
            }
            $html .= str_repeat($lightCell, $quiet);
            $html .= '</tr>';
        }

        // Bottom quiet zone
        $html .= str_repeat($quietRow, $quiet);

        $html .= '</table>';
        return $html;
    }

    public function renderBars(BarPattern $bars, RendererOptions $options): string
    {
        $scale = $options->scale;
        $fg = htmlspecialchars($options->foreground, ENT_QUOTES);
        $bg = htmlspecialchars($options->background, ENT_QUOTES);
        $quiet = $options->quietZone;

        $barHeight = $options->height ?? (int) ($bars->height() * $scale * 20);
        $quietWidth = $quiet * $scale;

        $html = '<table style="border-collapse:collapse;border-spacing:0;margin:0;padding:0;line-height:0"><tr>';

        // Left quiet zone
        if ($quietWidth > 0) {
            $html .= sprintf(
                '<td style="width:%dpx;height:%dpx;background:%s"></td>',
                $quietWidth,
                $barHeight,
                $bg,
            );
        }

        foreach ($bars->getBars() as $bar) {
            $barWidth = (int) round($bar->width * $scale);
            if ($barWidth < 1) {
                $barWidth = 1;
            }
            $color = $bar->dark ? $fg : $bg;
            $html .= sprintf(
                '<td style="width:%dpx;height:%dpx;background:%s"></td>',
                $barWidth,
                $barHeight,
                $color,
            );
        }

        // Right quiet zone
        if ($quietWidth > 0) {
            $html .= sprintf(
                '<td style="width:%dpx;height:%dpx;background:%s"></td>',
                $quietWidth,
                $barHeight,
                $bg,
            );
        }

        $html .= '</tr></table>';
        return $html;
    }
}
