# Usage Guide

## Architecture

The library has four layers:

1. **Encoder** converts data into a `ModuleMatrix` (2D) or `BarPattern` (1D)
2. **Renderer** draws encoded output as HTML, image, or PDF
3. **Reader** decodes a `ModuleMatrix` or `BarPattern` back into data *(interfaces only,  no implementation yet)*
4. **Semantics** bidirectional: builds structured payloads for encoding (`toPayload()`) and parses raw payloads back into typed objects (`fromPayload()`)

## Facade (quick one-liners)

```php
use Horde\Barcode\Barcode;

Barcode::qrHtml('https://example.com');
Barcode::qrHtml('data', scale: 5);
Barcode::code128Html('SKU-12345');
Barcode::eanHtml('5901234123457');
Barcode::dataMatrixHtml('Hello');
```

## Encoders

### QR Code

```php
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;

$encoder = new QrEncoder(ErrorCorrectionLevel::H);
$matrix = $encoder->encode('https://horde.org');

// $matrix->width(), $matrix->height(), $matrix->isDark($row, $col)
```

### Code 128

```php
use Horde\Barcode\Encoder\Code128Encoder;

$encoder = new Code128Encoder();
$pattern = $encoder->encode('INV-2026-0042');
```

### EAN/UPC

```php
use Horde\Barcode\Encoder\EanUpcEncoder;

$encoder = new EanUpcEncoder();
$pattern = $encoder->encode('5901234123457'); // EAN-13
$pattern = $encoder->encode('96385074');      // EAN-8
```

### Data Matrix

```php
use Horde\Barcode\Encoder\DataMatrixEncoder;

$encoder = new DataMatrixEncoder();
$matrix = $encoder->encode('Serial:ABC123');
```

### Code 39 / ITF-14

```php
use Horde\Barcode\Encoder\Code39Encoder;
use Horde\Barcode\Encoder\Itf14Encoder;

$pattern = (new Code39Encoder())->encode('HELLO');
$pattern = (new Itf14Encoder())->encode('12345678901231');
```

## Renderers

### HTML (standalone, no dependencies)

```php
use Horde\Barcode\Renderer\HtmlRenderer;
use Horde\Barcode\Renderer\RendererOptions;

$renderer = new HtmlRenderer();
$html = $renderer->renderMatrix($matrix, new RendererOptions(
    scale: 4,
    foreground: '#333333',
    background: '#FFFFFF',
    quietZone: 4,
));
```

### Image (requires horde/image)

```php
use Horde\Barcode\Renderer\ImageRenderer;
use Horde\Barcode\Renderer\RendererOptions;

// Any Horde_Image backend: GD, Imagick, or SVG
$image = new Horde_Image_Gd(['width' => 200, 'height' => 200]);
$renderer = new ImageRenderer($image);
$renderer->renderMatrix($matrix, new RendererOptions(scale: 3));

$png = $image->raw();
```

### PDF (requires horde/pdf)

```php
use Horde\Barcode\Renderer\PdfRenderer;
use Horde\Barcode\Renderer\RendererOptions;
use Horde\Pdf\PdfWriter;

$pdf = new PdfWriter();
$pdf->addPage();
$renderer = new PdfRenderer($pdf, x: 20.0, y: 50.0);
$renderer->renderMatrix($matrix, new RendererOptions(scale: 1));

$output = $pdf->output();
```

## Semantic Encoders

Semantic classes produce payload strings formatted for specific use cases.
Each exposes `toPayload()` and `recommendedEncoder()`.

### WiFi

```php
use Horde\Barcode\Semantics\WiFi;

$wifi = new WiFi(ssid: 'MyNetwork', password: 'secret', authType: 'WPA');
echo Barcode::qrHtml($wifi->toPayload());
```

### vCard / MeCard

```php
use Horde\Barcode\Semantics\VCard;

$contact = new VCard(name: 'Doe,John', phone: '+1555123456', email: 'john@example.com');
echo Barcode::qrHtml($contact->toPayload());
```

### OTP Provisioning

```php
use Horde\Barcode\Semantics\OtpProvisioning;

$otp = new OtpProvisioning(
    secret: 'JBSWY3DPEHPK3PXP',
    accountName: 'user@example.com',
    issuer: 'MyApp',
);
echo Barcode::qrHtml($otp->toPayload());
```

### SEPA EPC (European payments)

```php
use Horde\Barcode\Semantics\Payment\SepaEpc;

$sepa = new SepaEpc(
    beneficiaryName: 'Max Mustermann',
    iban: 'DE89370400440532013000',
    amount: 12.50,
    bic: 'COBADEFFXXX',
    remittanceText: 'Invoice 123',
);
echo Barcode::qrHtml($sepa->toPayload());
```

### UPI (Indian payments)

```php
use Horde\Barcode\Semantics\Payment\Upi;

$upi = new Upi(vpa: 'shop@upi', payeeName: 'My Shop', amount: 500.00);
echo Barcode::qrHtml($upi->toPayload());
```

### GS1 / GTIN

```php
use Horde\Barcode\Semantics\Gs1\Gtin;
use Horde\Barcode\Semantics\Gs1\DigitalLink;

$gtin = Gtin::withCheckDigit('590123412345');
echo Barcode::eanHtml($gtin->toPayload());

$dl = new DigitalLink(['01' => '09501101530003', '17' => '240101']);
echo Barcode::qrHtml($dl->toPayload());
```

### IATA Boarding Pass

```php
use Horde\Barcode\Semantics\Transport\IataBcbp;

$bcbp = new IataBcbp(
    passengerName: 'DOE/JOHN',
    pnr: 'ABC123',
    fromCity: 'JFK',
    toCity: 'LAX',
    carrier: 'AA',
    flightNumber: '1234',
    julianDate: 120,
);
// Boarding passes typically use PDF417
$encoder = new \Horde\Barcode\Encoder\Code128Encoder(); // or Pdf417 when available
$pattern = $encoder->encode($bcbp->toPayload());
```

## Decoding Payloads

Classes implementing `SemanticDecoderInterface` can detect and parse raw payloads:

```php
use Horde\Barcode\Semantics\WiFi;
use Horde\Barcode\Semantics\Payment\SepaEpc;

$raw = 'WIFI:T:WPA;S:MyNet;P:secret;;';

if (WiFi::canDecode($raw)) {
    $wifi = WiFi::fromPayload($raw);
}

$raw = "BCD\n002\n1\nSCT\n...";
if (SepaEpc::canDecode($raw)) {
    $sepa = SepaEpc::fromPayload($raw);
}
```

## Reader Interfaces (not yet implemented)

The `Horde\Barcode\Reader` namespace defines interfaces for decoding barcodes
from their visual representation (module matrices or bar patterns) back into
data strings. These are designed but awaiting implementation:

```php
use Horde\Barcode\Reader\ReaderInterface;
use Horde\Barcode\Reader\QrReaderInterface;
use Horde\Barcode\Reader\LinearReaderInterface;
use Horde\Barcode\Reader\ImageLocatorInterface;
```

- `ReaderInterface` is the base contract to decode from `ModuleMatrix` or `BarPattern`
- `QrReaderInterface` is the contract to decode QR codes from a `ModuleMatrix`
- `LinearReaderInterface` is the contract to decode 1D barcodes from a `BarPattern`
- `ImageLocatorInterface` finds barcode regions within a raster image (via horde/Image)

The intended workflow once implemented:

```php
// Future API (not yet available):
$locator = new SomeImageLocator($hordeImage);
$regions = $locator->locate($image);        // find barcode regions
$matrix = $regions[0]->toModuleMatrix();    // extract module grid
$data = $qrReader->decode($matrix);         // decode to string
$wifi = WiFi::fromPayload($data);           // interpret semantically
```

## Full Control Example

```php
use Horde\Barcode\Encoder\QrEncoder;
use Horde\Barcode\Encoder\Qr\ErrorCorrectionLevel;
use Horde\Barcode\Renderer\HtmlRenderer;
use Horde\Barcode\Renderer\RendererOptions;
use Horde\Barcode\Semantics\WiFi;

$wifi = new WiFi('GuestNet', 'welcome123', 'WPA2');

$encoder = new QrEncoder(ErrorCorrectionLevel::M);
$matrix = $encoder->encode($wifi->toPayload());

$renderer = new HtmlRenderer();
$html = $renderer->renderMatrix($matrix, new RendererOptions(
    scale: 5,
    foreground: '#1a1a2e',
    background: '#eaeaea',
    quietZone: 2,
));
```
