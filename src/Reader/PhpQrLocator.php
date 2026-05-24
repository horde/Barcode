<?php

declare(strict_types=1);

/**
 * Pure-PHP QR code locator using finder pattern detection.
 *
 * Requires horde/image for pixel access (GdDriver or ImagickDriver).
 * Binarizes the image, scans for the three QR finder patterns, then
 * extracts the module grid.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Exception\BarcodeException;

final class PhpQrLocator implements QrLocatorInterface
{
    public function __construct(
        private readonly int $threshold = 128,
    ) {}

    /** @return list<LocatedSymbol> */
    public function locateQr(string $imageData): array
    {
        $binaryImage = $this->binarize($imageData);
        if ($binaryImage === null) {
            return [];
        }

        [$pixels, $width, $height] = $binaryImage;

        $detector = new FinderPatternDetector();
        $finders = $detector->detect($pixels, $width, $height);

        if (count($finders) < 3) {
            return [];
        }

        $extractor = new QrGridExtractor();
        $matrix = $extractor->extract($pixels, $finders, $width, $height);

        if ($matrix === null) {
            return [];
        }

        // Compute bounding box from finder positions
        $bounds = $this->computeBounds($finders, $finders[0]['moduleSize'] ?? 1.0);

        // Attempt decode
        $decoder = new QrDecoder();
        try {
            $payload = $decoder->decode($matrix);
        } catch (\Throwable) {
            $payload = '';
        }

        if ($payload === '') {
            return [];
        }

        return [
            new LocatedSymbol(
                bounds: $bounds,
                payload: $payload,
                type: SymbolType::Qr,
                symbol: $matrix,
            ),
        ];
    }

    /**
     * Binarize image data into a 2D boolean array.
     *
     * @return array{array<int, array<int, bool>>, int, int}|null
     */
    private function binarize(string $imageData): ?array
    {
        $gd = @imagecreatefromstring($imageData);
        if ($gd === false) {
            return null;
        }

        $width = imagesx($gd);
        $height = imagesy($gd);

        $pixels = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($gd, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $row[] = $lum < $this->threshold;
            }
            $pixels[] = $row;
        }

        imagedestroy($gd);
        return [$pixels, $width, $height];
    }

    /**
     * @param list<array{x: float, y: float, moduleSize: float}> $finders
     */
    private function computeBounds(array $finders, float $moduleSize): BoundingBox
    {
        $minX = PHP_INT_MAX;
        $minY = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $maxY = PHP_INT_MIN;

        $halfFinder = 3.5 * $moduleSize;

        foreach ($finders as $f) {
            $minX = min($minX, (int) floor($f['x'] - $halfFinder));
            $minY = min($minY, (int) floor($f['y'] - $halfFinder));
            $maxX = max($maxX, (int) ceil($f['x'] + $halfFinder));
            $maxY = max($maxY, (int) ceil($f['y'] + $halfFinder));
        }

        return new BoundingBox(
            max(0, $minX),
            max(0, $minY),
            $maxX - $minX,
            $maxY - $minY,
        );
    }
}
