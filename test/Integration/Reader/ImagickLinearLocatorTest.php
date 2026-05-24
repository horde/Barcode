<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Integration\Reader;

use Horde\Barcode\Encoder\Code128Encoder;
use Horde\Barcode\Encoder\EanUpcEncoder;
use Horde\Barcode\Reader\ImagickLinearLocator;
use Horde\Barcode\Reader\SymbolType;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImagickLinearLocator::class)]
#[RequiresPhpExtension('imagick')]
final class ImagickLinearLocatorTest extends TestCase
{
    public function testDecodeCode128FromImage(): void
    {
        $input = 'Hello World';
        $png = $this->renderCode128($input);

        $locator = new ImagickLinearLocator();
        $results = $locator->locateLinear($png);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
        $this->assertSame(SymbolType::Code128, $results[0]->type);
    }

    public function testDecodeEan13FromImage(): void
    {
        $input = '5901234123457';
        $png = $this->renderEan13($input);

        $locator = new ImagickLinearLocator();
        $results = $locator->locateLinear($png);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
        $this->assertSame(SymbolType::Ean13, $results[0]->type);
    }

    public function testDecodeRotatedBarcode(): void
    {
        $input = 'Rotated';
        $png = $this->renderCode128($input, rotation: 15);

        $locator = new ImagickLinearLocator();
        $results = $locator->locateLinear($png);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
    }

    public function testReturnsEmptyForBlankImage(): void
    {
        $imagick = new Imagick();
        $imagick->newImage(200, 100, new ImagickPixel('white'));
        $imagick->setImageFormat('png');

        $locator = new ImagickLinearLocator();
        $results = $locator->locateLinear($imagick->getImageBlob());

        $this->assertSame([], $results);
    }

    public function testResultHasBoundingBox(): void
    {
        $png = $this->renderCode128('Test');

        $locator = new ImagickLinearLocator();
        $results = $locator->locateLinear($png);

        $this->assertCount(1, $results);
        $this->assertGreaterThan(0, $results[0]->bounds->width);
        $this->assertGreaterThan(0, $results[0]->bounds->height);
    }

    private function renderCode128(string $data, int $rotation = 0): string
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode($data);
        return $this->renderBars($pattern->getBars(), $rotation);
    }

    private function renderEan13(string $data): string
    {
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode($data);
        return $this->renderBars($pattern->getBars());
    }

    private function renderBars(array $bars, int $rotation = 0): string
    {
        $barWidth = 3;
        $height = 60;
        $quietZone = 40;
        $totalWidth = 0;
        foreach ($bars as $bar) {
            $totalWidth += (int) round($bar->width * $barWidth);
        }
        $imgWidth = $totalWidth + 2 * $quietZone;

        $imagick = new Imagick();
        $imagick->newImage($imgWidth, $height, new ImagickPixel('white'));
        $imagick->setImageFormat('png');

        $draw = new ImagickDraw();
        $draw->setFillColor(new ImagickPixel('black'));

        $x = $quietZone;
        foreach ($bars as $bar) {
            $w = (int) round($bar->width * $barWidth);
            if ($bar->dark) {
                $draw->rectangle($x, 0, $x + $w - 1, $height - 1);
            }
            $x += $w;
        }
        $imagick->drawImage($draw);

        if ($rotation !== 0) {
            $imagick->rotateImage(new ImagickPixel('white'), $rotation);
        }

        return $imagick->getImageBlob();
    }
}
