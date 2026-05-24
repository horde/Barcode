<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Reader\ImageLocatorInterface;
use Horde\Barcode\Reader\NullLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullLocator::class)]
final class NullLocatorTest extends TestCase
{
    public function testImplementsImageLocatorInterface(): void
    {
        $locator = new NullLocator();
        $this->assertInstanceOf(ImageLocatorInterface::class, $locator);
    }

    public function testLocateReturnsEmptyArray(): void
    {
        $locator = new NullLocator();
        $this->assertSame([], $locator->locate('any image data'));
    }

    public function testLocateQrReturnsEmptyArray(): void
    {
        $locator = new NullLocator();
        $this->assertSame([], $locator->locateQr('any image data'));
    }

    public function testLocateLinearReturnsEmptyArray(): void
    {
        $locator = new NullLocator();
        $this->assertSame([], $locator->locateLinear('any image data'));
    }
}
