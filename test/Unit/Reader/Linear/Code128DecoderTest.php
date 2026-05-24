<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader\Linear;

use Horde\Barcode\Encoder\Code128Encoder;
use Horde\Barcode\Reader\Linear\Code128Decoder;
use Horde\Barcode\Reader\SymbolType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Code128Decoder::class)]
final class Code128DecoderTest extends TestCase
{
    #[DataProvider('validCode128Provider')]
    public function testRoundTripDecode(string $input): void
    {
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode($input);
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * 3);
        }

        $decoder = new Code128Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result, "Failed to decode: $input");
        $this->assertSame($input, $result->payload);
        $this->assertSame(SymbolType::Code128, $result->type);
    }

    public static function validCode128Provider(): array
    {
        return [
            'alpha uppercase' => ['ABCDEF'],
            'alpha lowercase' => ['test'],
            'mixed case' => ['Hello'],
            'with space' => ['Hello World!'],
            'digits short' => ['1234'],
            'digits long (Code C)' => ['12345678'],
            'mixed alpha-digits' => ['ABC123'],
            'mixed with switch' => ['AB1234CD'],
            'single char' => ['Z'],
            'special chars' => ['$10.99'],
            'long string' => ['The quick brown fox'],
        ];
    }

    #[DataProvider('scaleProvider')]
    public function testDecodeAtVariousScales(int $scale): void
    {
        $input = 'Hello';
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode($input);
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * $scale);
        }

        $decoder = new Code128Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result, "Failed at scale $scale");
        $this->assertSame($input, $result->payload);
    }

    public static function scaleProvider(): array
    {
        return [
            'scale 1' => [1],
            'scale 2' => [2],
            'scale 3' => [3],
            'scale 5' => [5],
            'scale 7' => [7],
            'scale 10' => [10],
        ];
    }

    public function testDecodeWithNoise(): void
    {
        $input = 'ABC123';
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode($input);
        $bars = $pattern->getBars();

        srand(42);
        $widths = [];
        foreach ($bars as $bar) {
            $base = $bar->width * 5;
            $noise = (rand(0, 2) - 1); // -1, 0, or +1 pixel
            $widths[] = max(1, (int) round($base + $noise));
        }

        $decoder = new Code128Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result);
        $this->assertSame($input, $result->payload);
    }

    public function testReturnsNullOnGarbage(): void
    {
        $decoder = new Code128Decoder();
        $this->assertNull($decoder->tryDecode([5, 3, 8, 2, 7, 1, 4, 9, 2, 6]));
    }

    public function testReturnsNullOnTooFewElements(): void
    {
        $decoder = new Code128Decoder();
        $this->assertNull($decoder->tryDecode([2, 1, 2]));
    }

    public function testCodeCPureDigits(): void
    {
        $input = '0123456789';
        $encoder = new Code128Encoder();
        $pattern = $encoder->encode($input);
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * 3);
        }

        $decoder = new Code128Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result);
        $this->assertSame($input, $result->payload);
    }
}
