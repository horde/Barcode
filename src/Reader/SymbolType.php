<?php

declare(strict_types=1);

/**
 * Symbology type for a detected barcode or 2D code.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Reader;

enum SymbolType: string
{
    case Qr = 'qr';
    case DataMatrix = 'datamatrix';
    case Code128 = 'code128';
    case Code39 = 'code39';
    case Ean13 = 'ean13';
    case Ean8 = 'ean8';
    case UpcA = 'upca';
    case UpcE = 'upce';
    case Itf = 'itf';
    case Unknown = 'unknown';
}
