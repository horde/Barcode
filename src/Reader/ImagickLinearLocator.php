<?php

declare(strict_types=1);

/**
 * Locates and decodes linear (1D) barcodes using Imagick for CV and pure-PHP for decoding.
 *
 * Pipeline:
 * 1. Load image via horde/Image ImagickDriver
 * 2. Downscale to manageable width
 * 3. Rotation search: coarse sweep then fine refinement
 * 4. At best angle: extract scanlines, binarize, RLE, decode
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Reader\Linear\Code128Decoder;
use Horde\Barcode\Reader\Linear\DecodedBarcode;
use Horde\Barcode\Reader\Linear\Ean13Decoder;
use Horde\Barcode\Reader\Linear\RunLengthEncoder;
use Horde\Barcode\Reader\Linear\SignalBinarizer;
use Horde\Barcode\Reader\Linear\SymbologyDecoderInterface;
use Horde\Image\Color\Color;
use Horde\Image\Driver\ImagickDriver;
use Horde\Image\Driver\ImagickResource;
use Horde\Image\Filter\Grayscale;
use Horde\Image\Geometry\Size;

final class ImagickLinearLocator implements LinearLocatorInterface
{
    private const MAX_WIDTH = 800;
    private const COARSE_ANGLES = [-30, -25, -20, -15, -10, -5, 0, 5, 10, 15, 20, 25, 30];
    private const FINE_STEP = 1;
    private const FINE_RANGE = 3;
    private const SCANLINE_COUNT = 5;

    /** @var list<SymbologyDecoderInterface> */
    private readonly array $decoders;

    /**
     * @param list<SymbologyDecoderInterface>|null $decoders
     */
    public function __construct(
        ?array $decoders = null,
    ) {
        $this->decoders = $decoders ?? [new Ean13Decoder(), new Code128Decoder()];
    }

    /** @return list<LocatedSymbol> */
    public function locateLinear(string $imageData): array
    {
        $driver = new ImagickDriver();
        $resource = $driver->load($imageData);

        if (!$resource instanceof ImagickResource) {
            return [];
        }

        // Downscale for speed
        $size = $resource->size();
        if ($size->width > self::MAX_WIDTH) {
            $scale = self::MAX_WIDTH / $size->width;
            $resource = $resource->resize(new Size(
                self::MAX_WIDTH,
                $size->height * $scale,
            ));
        }

        // Convert to grayscale for signal extraction
        $gray = $resource->apply(new Grayscale());

        // Rotation search with decode attempts
        $results = $this->searchAndDecode($gray);

        $symbols = [];
        foreach ($results as $decoded) {
            $symbols[] = new LocatedSymbol(
                bounds: new BoundingBox(0, 0, (int) $resource->size()->width, (int) $resource->size()->height),
                payload: $decoded->payload,
                type: $decoded->type,
                symbol: $decoded->pattern,
            );
        }

        return $symbols;
    }

    /**
     * Search across rotation angles, returning decoded barcodes.
     *
     * @return list<DecodedBarcode>
     */
    private function searchAndDecode(ImagickResource $gray): array
    {
        $background = Color::named('white');

        // Try 0 degrees first (most common case)
        $results = $this->extractAndDecode($gray);
        if (count($results) > 0) {
            return $results;
        }

        // Coarse sweep
        foreach (self::COARSE_ANGLES as $angle) {
            if ($angle === 0) {
                continue;
            }
            $rotated = $gray->rotate((float) $angle, $background);
            $results = $this->extractAndDecode($rotated);
            if (count($results) > 0) {
                return $results;
            }
        }

        // Fine sweep around all coarse angles
        foreach (self::COARSE_ANGLES as $baseAngle) {
            $fineStart = $baseAngle - self::FINE_RANGE;
            $fineEnd = $baseAngle + self::FINE_RANGE;
            for ($angle = $fineStart; $angle <= $fineEnd; $angle += self::FINE_STEP) {
                if (in_array($angle, self::COARSE_ANGLES, true)) {
                    continue;
                }
                $rotated = $angle !== 0 ? $gray->rotate((float) $angle, $background) : $gray;
                $results = $this->extractAndDecode($rotated);
                if (count($results) > 0) {
                    return $results;
                }
            }
        }

        return [];
    }

    /**
     * Extract multiple scanlines, attempt decoding on each.
     *
     * @return list<DecodedBarcode>
     */
    private function extractAndDecode(ImagickResource $resource): array
    {
        $size = $resource->size();
        $width = (int) $size->width;
        $height = (int) $size->height;

        $binarizer = new SignalBinarizer();
        $rle = new RunLengthEncoder();
        $decoded = [];
        $seen = [];

        for ($line = 0; $line < self::SCANLINE_COUNT; $line++) {
            $y = (int) ($height * (0.3 + 0.4 * $line / max(1, self::SCANLINE_COUNT - 1)));
            if ($y >= $height) {
                continue;
            }

            $signal = [];
            for ($x = 0; $x < $width; $x++) {
                $lum = $resource->getLuminance($x, $y);
                $signal[] = $lum / 255.0;
            }

            $binary = $binarizer->binarize($signal);
            $runs = $rle->encode($binary);
            if (count($runs) < 10) {
                continue;
            }

            $widths = $rle->widths($runs);

            foreach ($this->decoders as $decoder) {
                $result = $decoder->tryDecode($widths);
                if ($result !== null && !isset($seen[$result->payload])) {
                    $decoded[] = $result;
                    $seen[$result->payload] = true;
                }
            }
        }

        return $decoded;
    }
}
