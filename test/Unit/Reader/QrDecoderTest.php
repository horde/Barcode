<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Exception\BarcodeException;
use Horde\Barcode\Reader\QrDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QrDecoder::class)]
final class QrDecoderTest extends TestCase
{
    public function testDecodeNumericMode(): void
    {
        $input = '12345678';
        $encoder = new QrEncoder();
        $matrix = $encoder->encode($input, ErrorCorrectionLevel::M);

        $decoder = new QrDecoder();
        $this->assertSame($input, $decoder->decode($matrix));
    }

    public function testDecodeAlphanumericMode(): void
    {
        $input = 'HELLO WORLD';
        $encoder = new QrEncoder();
        $matrix = $encoder->encode($input, ErrorCorrectionLevel::M);

        $decoder = new QrDecoder();
        $this->assertSame($input, $decoder->decode($matrix));
    }

    public function testDecodeByteMode(): void
    {
        $input = 'https://horde.org';
        $encoder = new QrEncoder();
        $matrix = $encoder->encode($input, ErrorCorrectionLevel::M);

        $decoder = new QrDecoder();
        $this->assertSame($input, $decoder->decode($matrix));
    }

    public function testDecodeAllEcLevels(): void
    {
        $input = 'TEST';
        $encoder = new QrEncoder();
        $decoder = new QrDecoder();

        foreach (ErrorCorrectionLevel::cases() as $ecLevel) {
            $matrix = $encoder->encode($input, $ecLevel);
            $this->assertSame($input, $decoder->decode($matrix), "Failed for EC level {$ecLevel->name}");
        }
    }

    public function testDecodeVersion2(): void
    {
        $input = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $encoder = new QrEncoder();
        $matrix = $encoder->encode($input, ErrorCorrectionLevel::M);

        $this->assertSame(25, $matrix->width());

        $decoder = new QrDecoder();
        $this->assertSame($input, $decoder->decode($matrix));
    }

    public function testDecodeVersion4(): void
    {
        $input = 'The quick brown fox jumps over the lazy dog';
        $encoder = new QrEncoder();
        $matrix = $encoder->encode($input, ErrorCorrectionLevel::L);

        $this->assertSame(33, $matrix->width());

        $decoder = new QrDecoder();
        $this->assertSame($input, $decoder->decode($matrix));
    }

    public function testInvalidMatrixSizeThrows(): void
    {
        $modules = array_fill(0, 10, array_fill(0, 10, false));
        $matrix = new ModuleMatrix($modules);

        $decoder = new QrDecoder();
        $this->expectException(BarcodeException::class);
        $decoder->decode($matrix);
    }
}
