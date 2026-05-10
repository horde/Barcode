<?php

declare(strict_types=1);

/**
 * Thrown when data exceeds the capacity of the chosen symbol size.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Exception;

class CapacityExceededException extends BarcodeException {}
