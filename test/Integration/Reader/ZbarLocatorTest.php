<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Integration\Reader;

use Horde\Barcode\Exception\BarcodeException;
use Horde\Barcode\Reader\LocatedSymbol;
use Horde\Barcode\Reader\SymbolType;
use Horde\Barcode\Reader\ZbarLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZbarLocator::class)]
final class ZbarLocatorTest extends TestCase
{
    private ?string $zbarBinary = null;
    private ?string $qrencodeBinary = null;

    protected function setUp(): void
    {
        $confFile = __DIR__ . '/../conf.php';
        if (!file_exists($confFile)) {
            $this->markTestSkipped('No integration test configuration found (copy conf.php.dist to conf.php)');
        }

        $conf = [];
        require $confFile;

        $this->zbarBinary = $conf['reader']['zbar']['binary'] ?? null;
        if ($this->zbarBinary === null || !is_executable($this->zbarBinary)) {
            $this->markTestSkipped("zbarimg binary not available at: {$this->zbarBinary}");
        }

        $this->qrencodeBinary = $conf['reader']['qrencode']['binary'] ?? null;
        if ($this->qrencodeBinary === null || !is_executable($this->qrencodeBinary)) {
            $this->markTestSkipped("qrencode binary not available at: {$this->qrencodeBinary}");
        }
    }

    public function testLocateQrRoundTrip(): void
    {
        $input = 'HELLO WORLD';
        $imageData = $this->generateQrImage($input);

        $locator = new ZbarLocator($this->zbarBinary);
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
        $this->assertSame(SymbolType::Qr, $results[0]->type);
    }

    public function testLocateQrNumericPayload(): void
    {
        $input = '1234567890';
        $imageData = $this->generateQrImage($input);

        $locator = new ZbarLocator($this->zbarBinary);
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
    }

    public function testLocateQrBytePayload(): void
    {
        $input = 'https://horde.org';
        $imageData = $this->generateQrImage($input);

        $locator = new ZbarLocator($this->zbarBinary);
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
    }

    public function testLocateReturnsEmptyForBlankImage(): void
    {
        $img = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        ob_start();
        imagepng($img);
        $imageData = ob_get_clean();
        imagedestroy($img);

        $locator = new ZbarLocator($this->zbarBinary);
        $this->assertSame([], $locator->locate($imageData));
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

        $locator = new ZbarLocator($this->zbarBinary);
        $this->assertSame([], $locator->locateQr($imageData));
    }

    public function testLocateLinearReturnsEmptyForQrOnlyImage(): void
    {
        $imageData = $this->generateQrImage('HELLO');

        $locator = new ZbarLocator($this->zbarBinary);
        $results = $locator->locateLinear($imageData);

        $this->assertSame([], $results);
    }

    public function testResultHasBoundingBox(): void
    {
        $imageData = $this->generateQrImage('BOUNDS');

        $locator = new ZbarLocator($this->zbarBinary);
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertGreaterThanOrEqual(0, $results[0]->bounds->x);
        $this->assertGreaterThanOrEqual(0, $results[0]->bounds->y);
        $this->assertGreaterThan(0, $results[0]->bounds->width);
        $this->assertGreaterThan(0, $results[0]->bounds->height);
    }

    public function testLocateQrVersion2(): void
    {
        $input = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $imageData = $this->generateQrImage($input);

        $locator = new ZbarLocator($this->zbarBinary);
        $results = $locator->locateQr($imageData);

        $this->assertCount(1, $results);
        $this->assertSame($input, $results[0]->payload);
    }

    public function testThrowsWhenBinaryNotFound(): void
    {
        $locator = new ZbarLocator('/nonexistent/path/to/zbarimg');

        $this->expectException(BarcodeException::class);
        $locator->locate('dummy');
    }

    private function generateQrImage(string $input): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'qr_test_');
        $cmd = sprintf(
            '%s -o %s %s',
            escapeshellarg($this->qrencodeBinary),
            escapeshellarg($tmpFile),
            escapeshellarg($input),
        );
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            @unlink($tmpFile);
            $this->fail("qrencode failed with exit code {$exitCode}");
        }

        $imageData = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $imageData;
    }
}
