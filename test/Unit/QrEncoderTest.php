<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit;

use Horde\Barcode\Barcode;
use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Exception\CapacityExceededException;
use Horde\Barcode\Exception\EncodingException;
use Horde\Barcode\Renderer\HtmlRenderer;
use Horde\Barcode\Renderer\RendererOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrEncoder::class)]
#[CoversClass(ModuleMatrix::class)]
#[CoversClass(HtmlRenderer::class)]
#[CoversClass(Barcode::class)]
class QrEncoderTest extends TestCase
{
    public function testEncodeNumericData(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::L);
        $matrix = $encoder->encode('01234567');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
        // Version 1 = 21x21 modules
        $this->assertSame(21, $matrix->width());
        $this->assertSame(21, $matrix->height());
    }

    public function testEncodeAlphanumericData(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::M);
        $matrix = $encoder->encode('HELLO WORLD');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
        $this->assertSame(21, $matrix->width());
    }

    public function testEncodeByteData(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::M);
        $matrix = $encoder->encode('https://example.com');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
        // Should fit in version 1 or 2
        $this->assertGreaterThanOrEqual(21, $matrix->width());
    }

    public function testEncodeUrl(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::H);
        $matrix = $encoder->encode('https://www.horde.org/');

        $this->assertInstanceOf(ModuleMatrix::class, $matrix);
        $this->assertGreaterThanOrEqual(21, $matrix->width());
    }

    public function testEncodeEmptyStringThrows(): void
    {
        $encoder = new QrEncoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('');
    }

    public function testEncodeOversizedDataThrows(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::H);
        $this->expectException(CapacityExceededException::class);
        $encoder->encode(str_repeat('A', 5000));
    }

    public function testFinderPatternsPresent(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::L);
        $matrix = $encoder->encode('1');

        // Top-left finder pattern: 7x7 with dark border, light inner, dark center
        // First row should be all dark for 7 modules
        for ($i = 0; $i < 7; $i++) {
            $this->assertTrue($matrix->isDark(0, $i), "Top-left finder row 0, col $i should be dark");
        }
        // Second row: dark, light(5), dark
        $this->assertTrue($matrix->isDark(1, 0));
        $this->assertFalse($matrix->isDark(1, 1));
        $this->assertTrue($matrix->isDark(1, 6));
    }

    public function testMatrixIsSquare(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::M);
        $matrix = $encoder->encode('Test data 123');

        $this->assertSame($matrix->width(), $matrix->height());
    }

    public function testHtmlRendererProducesTable(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::L);
        $matrix = $encoder->encode('TEST');

        $renderer = new HtmlRenderer();
        $html = $renderer->renderMatrix($matrix, new RendererOptions(scale: 2));

        $this->assertStringStartsWith('<table', $html);
        $this->assertStringEndsWith('</table>', $html);
        $this->assertStringContainsString('background:#000000', $html);
        $this->assertStringContainsString('background:#FFFFFF', $html);
    }

    public function testFacadeQrHtml(): void
    {
        $html = Barcode::qrHtml('https://example.com', 3);

        $this->assertStringStartsWith('<table', $html);
        $this->assertStringContainsString('<td', $html);
        $this->assertStringEndsWith('</table>', $html);
    }

    public function testVersionSelection(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::L);

        // Short numeric data should fit in version 1 (21x21)
        $matrix = $encoder->encode('12345');
        $this->assertSame(21, $matrix->width());

        // Longer data should require larger version
        $matrix = $encoder->encode(str_repeat('A', 100));
        $this->assertGreaterThan(21, $matrix->width());
    }

    public function testDifferentEcLevels(): void
    {
        $data = 'HELLO';

        $matrixL = (new QrEncoder(ErrorCorrectionLevel::L))->encode($data);
        $matrixH = (new QrEncoder(ErrorCorrectionLevel::H))->encode($data);

        // Higher EC may require larger version for same data
        $this->assertGreaterThanOrEqual($matrixL->width(), $matrixH->width());
    }

    public function testMinVersionRespected(): void
    {
        $encoder = new QrEncoder(ErrorCorrectionLevel::L, minVersion: 5);
        $matrix = $encoder->encode('1');

        // Version 5 = 37x37
        $this->assertSame(37, $matrix->width());
    }
}
