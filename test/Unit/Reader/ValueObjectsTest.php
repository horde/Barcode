<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit\Reader;

use Horde\Barcode\Encoder\ModuleMatrix;
use Horde\Barcode\Reader\BoundingBox;
use Horde\Barcode\Reader\LocatedSymbol;
use Horde\Barcode\Reader\SymbolType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoundingBox::class)]
#[CoversClass(LocatedSymbol::class)]
#[CoversClass(SymbolType::class)]
final class ValueObjectsTest extends TestCase
{
    public function testBoundingBoxProperties(): void
    {
        $box = new BoundingBox(10, 20, 100, 200);
        $this->assertSame(10, $box->x);
        $this->assertSame(20, $box->y);
        $this->assertSame(100, $box->width);
        $this->assertSame(200, $box->height);
    }

    public function testLocatedSymbolProperties(): void
    {
        $box = new BoundingBox(0, 0, 50, 50);
        $matrix = new ModuleMatrix([[true, false], [false, true]]);
        $symbol = new LocatedSymbol(
            bounds: $box,
            payload: 'HELLO',
            type: SymbolType::Qr,
            symbol: $matrix,
        );

        $this->assertSame($box, $symbol->bounds);
        $this->assertSame('HELLO', $symbol->payload);
        $this->assertSame(SymbolType::Qr, $symbol->type);
        $this->assertSame($matrix, $symbol->symbol);
    }

    public function testLocatedSymbolDefaultSymbolIsNull(): void
    {
        $symbol = new LocatedSymbol(
            bounds: new BoundingBox(0, 0, 10, 10),
            payload: 'test',
            type: SymbolType::Code128,
        );

        $this->assertNull($symbol->symbol);
    }

    public function testSymbolTypeValues(): void
    {
        $this->assertSame('qr', SymbolType::Qr->value);
        $this->assertSame('datamatrix', SymbolType::DataMatrix->value);
        $this->assertSame('code128', SymbolType::Code128->value);
        $this->assertSame('code39', SymbolType::Code39->value);
        $this->assertSame('ean13', SymbolType::Ean13->value);
        $this->assertSame('ean8', SymbolType::Ean8->value);
        $this->assertSame('upca', SymbolType::UpcA->value);
        $this->assertSame('upce', SymbolType::UpcE->value);
        $this->assertSame('itf', SymbolType::Itf->value);
        $this->assertSame('unknown', SymbolType::Unknown->value);
    }

    public function testSymbolTypeFromString(): void
    {
        $this->assertSame(SymbolType::Qr, SymbolType::from('qr'));
        $this->assertSame(SymbolType::Ean13, SymbolType::from('ean13'));
        $this->assertSame(SymbolType::Unknown, SymbolType::from('unknown'));
    }
}
