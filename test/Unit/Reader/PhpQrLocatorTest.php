<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Reader\PhpQrLocator;
use Horde\Barcode\Reader\SymbolType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpQrLocator::class)]
final class PhpQrLocatorTest extends TestCase
{
    public function testLocateQrRoundTrip(): void
    {
        $input = 'HELLO';
        $imageData = $this->encodeAndRender($input, ErrorCorrectionLevel::M, 10, 4);

        $locator = new PhpQrLocator();
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
    }

    public function testLocateQrDifferentScales(): void
    {
        $input = 'SCALE TEST';

        foreach ([5, 15] as $scale) {
            $imageData = $this->encodeAndRender($input, ErrorCorrectionLevel::M, $scale, 4);
            $locator = new PhpQrLocator();
            $results = $locator->locateQr($imageData);

            $this->assertCount(1, $results, "Failed at scale {$scale}");
            $this->assertSame($input, $results[0]->payload, "Payload mismatch at scale {$scale}");
        }
    }

    public function testLocateQrReturnsEmptyForBlankImage(): void
    {
        $img = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        $locator = new PhpQrLocator();
        $this->assertSame([], $locator->locateQr($imageData));
    }

    public function testLocateQrReturnsEmptyForInvalidData(): void
    {
        $locator = new PhpQrLocator();
        $this->assertSame([], $locator->locateQr('not an image'));
    }

    public function testLocateQrVersion2(): void
    {
        $input = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $imageData = $this->encodeAndRender($input, ErrorCorrectionLevel::M, 8, 4);

        $locator = new PhpQrLocator();
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
    }

    public function testResultHasBoundingBox(): void
    {
        $imageData = $this->encodeAndRender('BOX', ErrorCorrectionLevel::L, 10, 4);

        $locator = new PhpQrLocator();
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertGreaterThan(0, $results[0]->bounds->width);
        $this->assertGreaterThan(0, $results[0]->bounds->height);
    }

    public function testResultTypeIsQr(): void
    {
        $imageData = $this->encodeAndRender('TYPE', ErrorCorrectionLevel::L, 10, 4);

        $locator = new PhpQrLocator();
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame(SymbolType::Qr, $results[0]->type);
    }

    public function testResultContainsModuleMatrix(): void
    {
        $imageData = $this->encodeAndRender('MATRIX', ErrorCorrectionLevel::L, 10, 4);

        $locator = new PhpQrLocator();
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(ModuleMatrix::class, $results[0]->symbol);
    }

    private function encodeAndRender(string $input, ErrorCorrectionLevel $ecLevel, int $scale, int $quiet): string
    {
        $encoder = new QrEncoder();
        $matrix = $encoder->encode($input, $ecLevel);
        return $this->renderMatrixToPng($matrix, $scale, $quiet);
    }

    private function renderMatrixToPng(ModuleMatrix $matrix, int $scale, int $quiet): string
    {
        $size = $matrix->width();
        $imgSize = ($size + 2 * $quiet) * $scale;
        $img = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix->isDark($row, $col)) {
                    $px = ($col + $quiet) * $scale;
                    $py = ($row + $quiet) * $scale;
                    imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        return $imageData;
    }
}
