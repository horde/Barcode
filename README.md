# Horde Barcode

Barcode and QR code generation and reading library for PHP 8.1+.

Encodes data into 1D and 2D barcode symbologies and renders them as HTML, raster
images (via horde/Image), SVG or PDF (via horde/Pdf). Decodes barcodes from
module matrices and bar patterns back into structured data. Includes a semantic
layer for some popular structured payload formats (payments, contacts, WiFi, GS1,
healthcare, etc.) with bidirectional encode/decode support.

**Status:** Encoding and rendering are fully implemented. Reading/decoding from
images is designed (interfaces defined) but not yet implemented. Contributions
welcome. :)

## Quick Start

```php
use Horde\Barcode\Barcode;

// QR code as HTML table
echo Barcode::qrHtml('https://www.horde.org/');

// Code 128 as HTML
echo Barcode::code128Html('INV-2026-0042');

// EAN-13 as HTML
echo Barcode::eanHtml('5901234123457');

// Data Matrix as HTML
echo Barcode::dataMatrixHtml('Serial:ABC123');
```

## Requirements

- PHP 8.1+ with ext-mbstring
- No other required dependencies (HTML renderer is standalone)

### Optional

- `horde/image` for raster (PNG/JPEG) and SVG output
- `horde/pdf` for PDF output
- `horde/otp` for OTP provisioning URI generation

## Installation

```bash
composer require horde/barcode
```

## Supported Standards

### Barcode Symbologies

| Class | Standard | Common Name | Description |
|-------|----------|-------------|-------------|
| `QrEncoder` | [ISO/IEC 18004](https://www.iso.org/standard/62021.html) | QR Code | 2D matrix barcode, versions 1–40, EC levels L/M/Q/H |
| `DataMatrixEncoder` | [ISO/IEC 16022](https://www.iso.org/standard/44230.html) | Data Matrix | 2D matrix barcode, ECC200 error correction |
| `Code128Encoder` | [ISO/IEC 15417](https://www.iso.org/standard/43896.html) | Code 128 | High-density linear barcode, character sets A/B/C |
| `EanUpcEncoder` | [ISO/IEC 15420](https://www.iso.org/standard/46143.html) | EAN/UPC | Retail point-of-sale (EAN-13, EAN-8, UPC-A, UPC-E) |
| `Code39Encoder` | [ISO/IEC 16388](https://www.iso.org/standard/43897.html) | Code 39 | Alphanumeric linear barcode (A–Z, 0–9, symbols) |
| `Itf14Encoder` | [ISO/IEC 16390](https://www.iso.org/standard/43898.html) | ITF-14 | Interleaved 2-of-5 for shipping cartons |

### Semantic Payload Formats

| Class | Format | Use Case | Reference |
|-------|--------|----------|-----------|
| `OtpProvisioning` | otpauth:// URI | TOTP/HOTP provisioning | [Google Authenticator Key URI](https://github.com/google/google-authenticator/wiki/Key-Uri-Format) |
| `WiFi` | WIFI:…;; | WiFi network sharing | [WPA3 / Wi-Fi Alliance](https://www.wi-fi.org/) |
| `VCard` | MECARD:…;; | Contact sharing | [NTT DoCoMo MeCard](https://www.nttdocomo.co.jp/english/) |
| `Url` | HTTP/HTTPS URL | Web links | [RFC 3986](https://www.rfc-editor.org/rfc/rfc3986) |
| `Email` | mailto: URI | Email composition | [RFC 6068](https://www.rfc-editor.org/rfc/rfc6068) |
| `Sms` | smsto: URI | SMS composition | [RFC 5724](https://www.rfc-editor.org/rfc/rfc5724) |
| `Tel` | tel: URI | Phone dialing | [RFC 3966](https://www.rfc-editor.org/rfc/rfc3966) |
| `SepaEpc` | EPC069-12 | EU SEPA credit transfer | [European Payments Council](https://www.europeanpaymentscouncil.eu/document-library/guidance-documents/quick-response-code-guidelines-enable-data-capture-initiation) |
| `EmvQr` | EMVCo Merchant QR | Payment terminal QR | [EMVCo QR Specification](https://www.emvco.com/emv-technologies/qrcodes/) |
| `Pix` | BCB Pix | Brazilian instant payments | [Banco Central do Brasil](https://www.bcb.gov.br/estabilidadefinanceira/pix) |
| `Upi` | UPI Deep Link | Indian instant payments | [NPCI UPI](https://www.npci.org.in/what-we-do/upi/product-overview) |
| `Gtin` | GTIN-8/12/13/14 | Product identification | [GS1 GTIN](https://www.gs1.org/standards/id-keys/gtin) |
| `Gs1Barcode` | GS1 AI Element Strings | Supply chain data | [GS1 General Specifications](https://www.gs1.org/standards/barcodes-epcrfid-id-keys/gs1-general-specifications) |
| `DigitalLink` | GS1 Digital Link URI | Web-resolvable product IDs | [GS1 Digital Link](https://www.gs1.org/standards/gs1-digital-link) |
| `IataBcbp` | IATA Resolution 792 | Airline boarding passes | [IATA BCBP](https://www.iata.org/en/programs/passenger/common-use/) |
| `Udi` | FDA 21 CFR 830 / EU MDR | Medical device identification | [FDA UDI](https://www.fda.gov/medical-devices/unique-device-identification-system-udi-system) |
| `Hibc` | ANSI/HIBC 2.6 | Healthcare product labeling | [HIBCC](https://www.hibcc.org/) |

## Documentation

See [doc/USAGE.md](doc/USAGE.md) for full API documentation and examples.

## License

LGPL-2.1 — see [LICENSE](LICENSE).
