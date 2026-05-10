<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit;

use Horde\Barcode\Encoder\Bar;
use Horde\Barcode\Encoder\BarPattern;
use Horde\Barcode\Encoder\Code128Encoder;
use Horde\Barcode\Encoder\Code39Encoder;
use Horde\Barcode\Encoder\EanUpcEncoder;
use Horde\Barcode\Encoder\Itf14Encoder;
use Horde\Barcode\Exception\EncodingException;
use Horde\Barcode\Renderer\HtmlRenderer;
use Horde\Barcode\Renderer\RendererOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Code128Encoder::class)]
#[CoversClass(Code39Encoder::class)]
#[CoversClass(EanUpcEncoder::class)]
#[CoversClass(Itf14Encoder::class)]
#[CoversClass(BarPattern::class)]
#[CoversClass(Bar::class)]
class LinearEncoderTest extends TestCase
{
    public function testCode128EncodesNumericString(): void
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode('12345678');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testCode128EncodesAlphanumeric(): void
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode('Hello World');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testCode128EmptyThrows(): void
    {
        $encoder = new Code128Encoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('');
    }

    public function testCode128TotalWidth(): void
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode('Test');

        $total = $pattern->totalWidth();
        $this->assertGreaterThan(0, $total);

        $sum = 0.0;
        foreach ($pattern->getBars() as $bar) {
            $sum += $bar->width;
        }
        $this->assertEqualsWithDelta($total, $sum, 0.001);
    }

    public function testEanUpc13Digit(): void
    {
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode('5901234123457');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testEanUpc12DigitAddsCheckDigit(): void
    {
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode('590123412345');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testEanUpc8Digit(): void
    {
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode('96385074');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testEanUpcInvalidLengthThrows(): void
    {
        $encoder = new EanUpcEncoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('123');
    }

    public function testEanUpcNonNumericThrows(): void
    {
        $encoder = new EanUpcEncoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('123456789A12');
    }

    public function testCode39EncodesAlphanumeric(): void
    {
        $encoder = new Code39Encoder();
        $pattern = $encoder->encode('HELLO');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testCode39InvalidCharThrows(): void
    {
        $encoder = new Code39Encoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('hello{invalid}');
    }

    public function testItf14EncodesNumericPairs(): void
    {
        $encoder = new Itf14Encoder();
        $pattern = $encoder->encode('12345678901231');

        $this->assertInstanceOf(BarPattern::class, $pattern);
        $this->assertGreaterThan(0, $pattern->count());
    }

    public function testItf14NonNumericThrows(): void
    {
        $encoder = new Itf14Encoder();
        $this->expectException(EncodingException::class);
        $encoder->encode('ABC123');
    }

    public function testHtmlRendererRendersBars(): void
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode('Test');

        $renderer = new HtmlRenderer();
        $html = $renderer->renderBars($pattern, new RendererOptions(scale: 2));

        $this->assertStringStartsWith('<table', $html);
        $this->assertStringEndsWith('</table>', $html);
        $this->assertStringContainsString('background:#000000', $html);
    }
}
