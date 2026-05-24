<?php

declare(strict_types=1);

/**
 * Extracts a QR code module grid from a binarized image using
 * finder pattern positions to determine sampling coordinates.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Encoder\Qr\Version;

final class QrGridExtractor
{
    /**
     * Extract a ModuleMatrix from a binary image using finder pattern centers.
     *
     * @param array<int, array<int, bool>> $binaryImage
     * @param list<array{x: float, y: float, moduleSize: float}> $finders Three finder pattern centers
     */
    public function extract(array $binaryImage, array $finders, int $imgWidth, int $imgHeight): ?ModuleMatrix
    {
        if (count($finders) < 3) {
            return null;
        }

        // Order the three finder patterns: top-left, top-right, bottom-left
        $ordered = $this->orderFinderPatterns($finders);
        [$topLeft, $topRight, $bottomLeft] = $ordered;

        $moduleSize = ($topLeft['moduleSize'] + $topRight['moduleSize'] + $bottomLeft['moduleSize']) / 3.0;

        // Estimate version from distance between finders
        $topDistance = $this->distance($topLeft, $topRight);
        $estimatedModules = (int) round($topDistance / $moduleSize) + 7;
        $version = (int) round(($estimatedModules - 17) / 4.0);
        $version = max(1, min(40, $version));
        $size = Version::size($version);

        // Compute the top-left corner of the QR code (3.5 modules before finder center)
        $originX = $topLeft['x'] - 3.5 * $moduleSize;
        $originY = $topLeft['y'] - 3.5 * $moduleSize;

        // Compute unit vectors along top edge and left edge
        $topDx = ($topRight['x'] - $topLeft['x']) / ($size - 7);
        $topDy = ($topRight['y'] - $topLeft['y']) / ($size - 7);
        $leftDx = ($bottomLeft['x'] - $topLeft['x']) / ($size - 7);
        $leftDy = ($bottomLeft['y'] - $topLeft['y']) / ($size - 7);

        // Sample each module
        $modules = [];
        for ($row = 0; $row < $size; $row++) {
            $moduleRow = [];
            for ($col = 0; $col < $size; $col++) {
                // Bilinear sample position
                $sampleX = $originX + ($col + 0.5) * $topDx + ($row + 0.5) * $leftDx;
                $sampleY = $originY + ($col + 0.5) * $topDy + ($row + 0.5) * $leftDy;

                $px = (int) round($sampleX);
                $py = (int) round($sampleY);

                if ($px >= 0 && $px < $imgWidth && $py >= 0 && $py < $imgHeight) {
                    $moduleRow[] = $binaryImage[$py][$px];
                } else {
                    $moduleRow[] = false;
                }
            }
            $modules[] = $moduleRow;
        }

        return new ModuleMatrix($modules);
    }

    /**
     * Order finder patterns as [top-left, top-right, bottom-left].
     *
     * The top-left finder is the one opposite the longest side of the
     * triangle formed by the three finders.
     *
     * @param list<array{x: float, y: float, moduleSize: float}> $finders
     * @return list<array{x: float, y: float, moduleSize: float}>
     */
    private function orderFinderPatterns(array $finders): array
    {
        $d01 = $this->distanceSquared($finders[0], $finders[1]);
        $d02 = $this->distanceSquared($finders[0], $finders[2]);
        $d12 = $this->distanceSquared($finders[1], $finders[2]);

        // The two farthest apart are top-right and bottom-left
        if ($d01 >= $d02 && $d01 >= $d12) {
            // finders[2] is top-left
            $topLeft = $finders[2];
            $a = $finders[0];
            $b = $finders[1];
        } elseif ($d02 >= $d01 && $d02 >= $d12) {
            // finders[1] is top-left
            $topLeft = $finders[1];
            $a = $finders[0];
            $b = $finders[2];
        } else {
            // finders[0] is top-left
            $topLeft = $finders[0];
            $a = $finders[1];
            $b = $finders[2];
        }

        // Determine which is top-right vs bottom-left using cross product.
        // In image coords (y-down), cross > 0 means a is clockwise from b
        // relative to topLeft, i.e. a is to the right (top-right).
        $cross = ($a['x'] - $topLeft['x']) * ($b['y'] - $topLeft['y'])
               - ($a['y'] - $topLeft['y']) * ($b['x'] - $topLeft['x']);

        if ($cross > 0) {
            return [$topLeft, $a, $b];
        }
        return [$topLeft, $b, $a];
    }

    private function distance(array $a, array $b): float
    {
        return sqrt(($a['x'] - $b['x']) ** 2 + ($a['y'] - $b['y']) ** 2);
    }

    private function distanceSquared(array $a, array $b): float
    {
        return ($a['x'] - $b['x']) ** 2 + ($a['y'] - $b['y']) ** 2;
    }
}
