<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader\Linear;

use Horde\Barcode\Encoder\EanUpcEncoder;
use Horde\Barcode\Reader\Linear\Ean13Decoder;
use Horde\Barcode\Reader\SymbolType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ean13Decoder::class)]
final class Ean13DecoderTest extends TestCase
{
    #[DataProvider('validEan13Provider')]
    public function testRoundTripDecode(string $ean): void
    {
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode($ean);
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * 3);
        }

        $decoder = new Ean13Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result, "Failed to decode EAN-13: $ean");
        $this->assertSame($ean, $result->payload);
        $this->assertSame(SymbolType::Ean13, $result->type);
    }

    public static function validEan13Provider(): array
    {
        return [
            'starts with 0' => ['0012345678905'],
            'starts with 5' => ['5901234123457'],
            'starts with 9' => ['9780201379624'],
            'all same digit' => ['0000000000000'],
            'ISBN example' => ['9781234567897'],
        ];
    }

    #[DataProvider('scaleProvider')]
    public function testDecodeAtVariousScales(int $scale): void
    {
        $ean = '5901234123457';
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode($ean);
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * $scale);
        }

        $decoder = new Ean13Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result, "Failed at scale $scale");
        $this->assertSame($ean, $result->payload);
    }

    public static function scaleProvider(): array
    {
        return [
            'scale 1' => [1],
            'scale 2' => [2],
            'scale 3' => [3],
            'scale 5' => [5],
            'scale 8' => [8],
            'scale 10' => [10],
        ];
    }

    public function testDecodeWithLeadingQuietZone(): void
    {
        $ean = '5901234123457';
        $encoder = new EanUpcEncoder();
        $pattern = $encoder->encode($ean);
        $bars = $pattern->getBars();

        $widths = [];
        foreach ($bars as $bar) {
            $widths[] = (int) round($bar->width * 3);
        }

        // Prepend quiet zone (white space)
        array_unshift($widths, 30);

        $decoder = new Ean13Decoder();
        $result = $decoder->tryDecode($widths);

        $this->assertNotNull($result);
        $this->assertSame($ean, $result->payload);
    }

    public function testReturnsNullOnGarbage(): void
    {
        $decoder = new Ean13Decoder();
        $result = $decoder->tryDecode([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $this->assertNull($result);
    }

    public function testReturnsNullOnTooFewElements(): void
    {
        $decoder = new Ean13Decoder();
        $this->assertNull($decoder->tryDecode([1, 1, 1]));
    }
}
