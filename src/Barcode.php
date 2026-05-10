<?php

declare(strict_types=1);

/**
 * Top-level facade for barcode generation.
 *
 * Provides convenience methods for common barcode generation tasks.
 * For full control, use the encoder and renderer classes directly.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode;

use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\Code128Encoder;
use Horde\Barcode\Encoder\DataMatrixEncoder;
use Horde\Barcode\Encoder\EanUpcEncoder;
use Horde\Barcode\Encoder\EncoderInterface;
use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Renderer\HtmlRenderer;
use Horde\Barcode\Renderer\RendererInterface;
use Horde\Barcode\Renderer\RendererOptions;

final class Barcode
{
    public static function qrHtml(string $data, int $scale = 3, ErrorCorrectionLevel $ecLevel = ErrorCorrectionLevel::M): string
    {
        $encoder = new QrEncoder($ecLevel);
        $matrix = $encoder->encode($data);
        $renderer = new HtmlRenderer();
        return $renderer->renderMatrix($matrix, new RendererOptions(scale: $scale));
    }

    public static function code128Html(string $data, int $scale = 2): string
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode($data);
        $renderer = new HtmlRenderer();
        return $renderer->renderBars($pattern, new RendererOptions(scale: $scale));
    }

    public static function eanHtml(string $data, int $scale = 2): string
    {
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode($data);
        $renderer = new HtmlRenderer();
        return $renderer->renderBars($pattern, new RendererOptions(scale: $scale));
    }

    public static function dataMatrixHtml(string $data, int $scale = 3): string
    {
        $encoder = new DataMatrixEncoder();
        $matrix = $encoder->encode($data);
        $renderer = new HtmlRenderer();
        return $renderer->renderMatrix($matrix, new RendererOptions(scale: $scale));
    }

    public static function encode(string $data, EncoderInterface $encoder): ModuleMatrix|BarPattern
    {
        return $encoder->encode($data);
    }

    public static function render(
        ModuleMatrix|BarPattern $encoded,
        RendererInterface $renderer,
        ?RendererOptions $options = null,
    ): string {
        $options ??= new RendererOptions();
        if ($encoded instanceof ModuleMatrix) {
            return $renderer->renderMatrix($encoded, $options);
        }
        return $renderer->renderBars($encoded, $options);
    }
}
