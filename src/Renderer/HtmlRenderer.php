<?php

declare(strict_types=1);

/**
 * Renders barcodes as HTML tables with CSS class names.
 *
 * No external dependencies required. Each module becomes a table cell.
 * In 'builtin' mode a <style> block is prepended; in 'external' mode
 * only class names are emitted (caller provides CSS).
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
    /**
     * @param string $style 'builtin' emits a <style> block; 'external' emits only class names
     */
    public function __construct(
        private readonly string $style = 'builtin',
    ) {}

    public function renderMatrix(ModuleMatrix $matrix, RendererOptions $options): string
    {
        $scale = $options->scale;
        $fg = htmlspecialchars($options->foreground, ENT_QUOTES);
        $bg = htmlspecialchars($options->background, ENT_QUOTES);
        $quiet = $options->quietZone;
        $totalWidth = ($matrix->width() + $quiet * 2) * $scale;

        $html = '';

        if ($this->style === 'builtin') {
            $html .= '<style>'
                . '.horde-bc{border-collapse:collapse;border-spacing:0;margin:0;padding:0;line-height:0;font-size:0}'
                . sprintf('.horde-bc td{width:%dpx;height:%dpx;padding:0}', $scale, $scale)
                . sprintf('.horde-bc-d{background:%s}', $fg)
                . sprintf('.horde-bc-l{background:%s}', $bg)
                . '</style>';
        }

        $html .= sprintf('<table class="horde-bc" style="width:%dpx">', $totalWidth);

        $darkCell = '<td class="horde-bc-d"></td>';
        $lightCell = '<td class="horde-bc-l"></td>';

        $quietRow = '<tr>' . str_repeat($lightCell, $matrix->width() + $quiet * 2) . '</tr>';
        $html .= str_repeat($quietRow, $quiet);

        for ($row = 0; $row < $matrix->height(); $row++) {
            $html .= '<tr>';
            $html .= str_repeat($lightCell, $quiet);
            for ($col = 0; $col < $matrix->width(); $col++) {
                $html .= $matrix->isDark($row, $col) ? $darkCell : $lightCell;
            }
            $html .= str_repeat($lightCell, $quiet);
            $html .= '</tr>';
        }

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

        $html = '';

        if ($this->style === 'builtin') {
            $html .= '<style>'
                . '.horde-bc{border-collapse:collapse;border-spacing:0;margin:0;padding:0;line-height:0;font-size:0}'
                . '.horde-bc td{padding:0}'
                . sprintf('.horde-bc-d{background:%s}', $fg)
                . sprintf('.horde-bc-l{background:%s}', $bg)
                . '</style>';
        }

        $html .= '<table class="horde-bc"><tr>';

        if ($quietWidth > 0) {
            $html .= sprintf(
                '<td class="horde-bc-l" style="width:%dpx;height:%dpx"></td>',
                $quietWidth,
                $barHeight,
            );
        }

        foreach ($bars->getBars() as $bar) {
            $barWidth = (int) round($bar->width * $scale);
            if ($barWidth < 1) {
                $barWidth = 1;
            }
            $class = $bar->dark ? 'horde-bc-d' : 'horde-bc-l';
            $html .= sprintf(
                '<td class="%s" style="width:%dpx;height:%dpx"></td>',
                $class,
                $barWidth,
                $barHeight,
            );
        }

        if ($quietWidth > 0) {
            $html .= sprintf(
                '<td class="horde-bc-l" style="width:%dpx;height:%dpx"></td>',
                $quietWidth,
                $barHeight,
            );
        }

        $html .= '</tr></table>';
        return $html;
    }
}
