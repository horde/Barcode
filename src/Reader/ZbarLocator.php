<?php

declare(strict_types=1);

/**
 * Locator that wraps the zbarimg command-line tool.
 *
 * Requires the zbar-tools package (provides /usr/bin/zbarimg).
 * Supports both QR codes and linear barcodes in a single scan pass.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

use Horde\Barcode\Exception\BarcodeException;

final class ZbarLocator implements ImageLocatorInterface
{
    private const ZBAR_TYPE_MAP = [
        'QR-Code' => SymbolType::Qr,
        'EAN-13' => SymbolType::Ean13,
        'EAN-8' => SymbolType::Ean8,
        'UPC-A' => SymbolType::UpcA,
        'UPC-E' => SymbolType::UpcE,
        'CODE-128' => SymbolType::Code128,
        'CODE-39' => SymbolType::Code39,
        'I2/5' => SymbolType::Itf,
    ];

    public function __construct(
        private readonly string $binary = '/usr/bin/zbarimg',
    ) {}

    /** @return list<LocatedSymbol> */
    public function locate(string $imageData): array
    {
        $this->assertBinaryExists();

        $tmpFile = $this->writeTempFile($imageData);

        try {
            $xml = $this->execute($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        if ($xml === '') {
            return [];
        }

        return $this->parseXml($xml);
    }

    /** @return list<LocatedSymbol> */
    public function locateQr(string $imageData): array
    {
        return array_values(array_filter(
            $this->locate($imageData),
            static fn (LocatedSymbol $s): bool => $s->type === SymbolType::Qr,
        ));
    }

    /** @return list<LocatedSymbol> */
    public function locateLinear(string $imageData): array
    {
        return array_values(array_filter(
            $this->locate($imageData),
            static fn (LocatedSymbol $s): bool => $s->type !== SymbolType::Qr
                && $s->type !== SymbolType::DataMatrix,
        ));
    }

    private function assertBinaryExists(): void
    {
        if (!is_executable($this->binary)) {
            throw new BarcodeException(sprintf(
                'zbarimg binary not found or not executable: %s',
                $this->binary,
            ));
        }
    }

    private function writeTempFile(string $imageData): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zbar_');
        if ($path === false) {
            throw new BarcodeException('Failed to create temporary file for zbarimg');
        }
        file_put_contents($path, $imageData);
        return $path;
    }

    private function execute(string $filePath): string
    {
        $cmd = sprintf(
            '%s --xml --quiet %s 2>/dev/null',
            escapeshellarg($this->binary),
            escapeshellarg($filePath),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        // Exit code 4 = no barcode found (not an error)
        if ($exitCode === 4) {
            return '';
        }

        if ($exitCode !== 0) {
            throw new BarcodeException(sprintf(
                'zbarimg failed with exit code %d: %s',
                $exitCode,
                implode("\n", $output),
            ));
        }

        return implode("\n", $output);
    }

    /** @return list<LocatedSymbol> */
    private function parseXml(string $xml): array
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadXML($xml);
        libxml_use_internal_errors($previous);

        $results = [];
        $symbols = $doc->getElementsByTagName('symbol');

        foreach ($symbols as $symbol) {
            $type = $symbol->getAttribute('type');
            $symbolType = self::ZBAR_TYPE_MAP[$type] ?? SymbolType::Unknown;

            $data = '';
            $dataNodes = $symbol->getElementsByTagName('data');
            if ($dataNodes->length > 0) {
                $data = $dataNodes->item(0)->textContent;
            }

            $bounds = $this->parsePolygon($symbol);

            $results[] = new LocatedSymbol(
                bounds: $bounds,
                payload: $data,
                type: $symbolType,
            );
        }

        return $results;
    }

    private function parsePolygon(\DOMElement $symbol): BoundingBox
    {
        $polygons = $symbol->getElementsByTagName('polygon');
        if ($polygons->length === 0) {
            return new BoundingBox(0, 0, 0, 0);
        }

        $points = $polygons->item(0)->getAttribute('points');
        if ($points === '') {
            return new BoundingBox(0, 0, 0, 0);
        }

        $coords = preg_split('/\s+/', trim($points));
        $minX = PHP_INT_MAX;
        $minY = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $maxY = PHP_INT_MIN;

        foreach ($coords as $coord) {
            // Format: +12,+75 or -3,+10
            if (preg_match('/^([+-]?\d+),([+-]?\d+)$/', $coord, $m)) {
                $x = (int) $m[1];
                $y = (int) $m[2];
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($minX === PHP_INT_MAX) {
            return new BoundingBox(0, 0, 0, 0);
        }

        return new BoundingBox($minX, $minY, $maxX - $minX, $maxY - $minY);
    }
}
