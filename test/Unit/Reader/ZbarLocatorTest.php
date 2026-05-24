<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Exception\BarcodeException;
use Horde\Barcode\Reader\ImageLocatorInterface;
use Horde\Barcode\Reader\QrLocatorInterface;
use Horde\Barcode\Reader\LinearLocatorInterface;
use Horde\Barcode\Reader\ZbarLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZbarLocator::class)]
final class ZbarLocatorTest extends TestCase
{
    public function testImplementsImageLocatorInterface(): void
    {
        $locator = new ZbarLocator();
        $this->assertInstanceOf(ImageLocatorInterface::class, $locator);
    }

    public function testImplementsQrLocatorInterface(): void
    {
        $locator = new ZbarLocator();
        $this->assertInstanceOf(QrLocatorInterface::class, $locator);
    }

    public function testImplementsLinearLocatorInterface(): void
    {
        $locator = new ZbarLocator();
        $this->assertInstanceOf(LinearLocatorInterface::class, $locator);
    }

    public function testThrowsWhenBinaryNotFound(): void
    {
        $locator = new ZbarLocator('/nonexistent/path/to/zbarimg');

        $this->expectException(BarcodeException::class);
        $this->expectExceptionMessage('not found or not executable');
        $locator->locate('dummy image data');
    }
}
