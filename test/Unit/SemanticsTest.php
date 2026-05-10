<?php

declare(strict_types=1);

namespace Horde\Barcode\Test\Unit;

use Horde\Barcode\Semantics\Email;
use Horde\Barcode\Semantics\FreeText;
use Horde\Barcode\Semantics\OtpProvisioning;
use Horde\Barcode\Semantics\Sms;
use Horde\Barcode\Semantics\Tel;
use Horde\Barcode\Semantics\Url;
use Horde\Barcode\Semantics\VCard;
use Horde\Barcode\Semantics\WiFi;
use Horde\Barcode\Semantics\Gs1\DigitalLink;
use Horde\Barcode\Semantics\Gs1\Gtin;
use Horde\Barcode\Semantics\Payment\EmvQr;
use Horde\Barcode\Semantics\Payment\Pix;
use Horde\Barcode\Semantics\Payment\SepaEpc;
use Horde\Barcode\Semantics\Payment\Upi;
use Horde\Barcode\Semantics\Transport\IataBcbp;
use Horde\Barcode\Semantics\Healthcare\Hibc;
use Horde\Barcode\Semantics\Healthcare\Udi;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WiFi::class)]
#[CoversClass(VCard::class)]
#[CoversClass(Url::class)]
#[CoversClass(Email::class)]
#[CoversClass(Sms::class)]
#[CoversClass(Tel::class)]
#[CoversClass(FreeText::class)]
#[CoversClass(OtpProvisioning::class)]
#[CoversClass(DigitalLink::class)]
#[CoversClass(Gtin::class)]
#[CoversClass(EmvQr::class)]
#[CoversClass(SepaEpc::class)]
#[CoversClass(Upi::class)]
#[CoversClass(IataBcbp::class)]
#[CoversClass(Hibc::class)]
#[CoversClass(Udi::class)]
class SemanticsTest extends TestCase
{
    public function testWiFiPayload(): void
    {
        $wifi = new WiFi('MyNetwork', 'secret123', 'WPA');
        $payload = $wifi->toPayload();

        $this->assertStringStartsWith('WIFI:', $payload);
        $this->assertStringContainsString('S:MyNetwork', $payload);
        $this->assertStringContainsString('P:secret123', $payload);
        $this->assertStringContainsString('T:WPA', $payload);
    }

    public function testWiFiRoundTrip(): void
    {
        $wifi = new WiFi('Test Net', 'pass word', 'WPA2');
        $payload = $wifi->toPayload();

        $this->assertTrue(WiFi::canDecode($payload));
        $decoded = WiFi::fromPayload($payload);
        $this->assertSame($wifi->toPayload(), $decoded->toPayload());
    }

    public function testVCardPayload(): void
    {
        $vcard = new VCard('Doe,John', '+1555123456', 'john@example.com');
        $payload = $vcard->toPayload();

        $this->assertStringStartsWith('MECARD:', $payload);
        $this->assertStringContainsString('N:Doe,John', $payload);
        $this->assertStringContainsString('TEL:+1555123456', $payload);
        $this->assertStringContainsString('EMAIL:john@example.com', $payload);
    }

    public function testVCardRoundTrip(): void
    {
        $vcard = new VCard('Smith,Jane', '+49123456', 'jane@test.de');
        $payload = $vcard->toPayload();

        $this->assertTrue(VCard::canDecode($payload));
        $decoded = VCard::fromPayload($payload);
        $this->assertSame($payload, $decoded->toPayload());
    }

    public function testUrlPayload(): void
    {
        $url = new Url('https://www.horde.org/');
        $this->assertSame('https://www.horde.org/', $url->toPayload());
    }

    public function testUrlRoundTrip(): void
    {
        $payload = 'https://example.com/path?q=1';
        $this->assertTrue(Url::canDecode($payload));
        $decoded = Url::fromPayload($payload);
        $this->assertSame($payload, $decoded->toPayload());
    }

    public function testEmailPayload(): void
    {
        $email = new Email('user@example.com', 'Hello', 'Body text');
        $payload = $email->toPayload();

        $this->assertStringStartsWith('mailto:', $payload);
        $this->assertStringContainsString('user@example.com', $payload);
        $this->assertStringContainsString('subject=Hello', $payload);
    }

    public function testSmsPayload(): void
    {
        $sms = new Sms('+1234567890', 'Hello there');
        $payload = $sms->toPayload();

        $this->assertStringStartsWith('smsto:', $payload);
        $this->assertStringContainsString('+1234567890', $payload);
    }

    public function testTelPayload(): void
    {
        $tel = new Tel('+1234567890');
        $this->assertSame('tel:+1234567890', $tel->toPayload());
    }

    public function testFreeTextPayload(): void
    {
        $text = new FreeText('Arbitrary data');
        $this->assertSame('Arbitrary data', $text->toPayload());
    }

    public function testOtpProvisioningPayload(): void
    {
        $otp = new OtpProvisioning('JBSWY3DPEHPK3PXP', 'user@example.com', 'Horde');
        $payload = $otp->toPayload();

        $this->assertStringStartsWith('otpauth://totp/', $payload);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $payload);
        $this->assertStringContainsString('issuer=Horde', $payload);
    }

    public function testOtpProvisioningRoundTrip(): void
    {
        $otp = new OtpProvisioning('ABCDEFGH', 'admin@test.org', 'TestApp', digits: 8, period: 60);
        $payload = $otp->toPayload();

        $this->assertTrue(OtpProvisioning::canDecode($payload));
        $decoded = OtpProvisioning::fromPayload($payload);
        $this->assertSame($payload, $decoded->toPayload());
    }

    public function testDigitalLinkPayload(): void
    {
        $dl = new DigitalLink(['01' => '09501101530003', '17' => '240101']);
        $payload = $dl->toPayload();

        $this->assertStringStartsWith('https://id.gs1.org/', $payload);
        $this->assertStringContainsString('/01/09501101530003', $payload);
        $this->assertStringContainsString('/17/240101', $payload);
    }

    public function testDigitalLinkRoundTrip(): void
    {
        $dl = new DigitalLink(['01' => '09501101530003', '17' => '240101']);
        $payload = $dl->toPayload();

        $this->assertTrue(DigitalLink::canDecode($payload));
        $decoded = DigitalLink::fromPayload($payload);
        $this->assertSame(['01' => '09501101530003', '17' => '240101'], $decoded->getIdentifiers());
    }

    public function testGtinCheckDigit(): void
    {
        $gtin = Gtin::withCheckDigit('590123412345');
        $payload = $gtin->toPayload();

        $this->assertSame(13, strlen($payload));
        $this->assertSame('5901234123457', $payload);
    }

    public function testSepaEpcPayload(): void
    {
        $sepa = new SepaEpc('Max Mustermann', 'DE89370400440532013000', 12.50);
        $payload = $sepa->toPayload();

        $this->assertStringStartsWith("BCD\n", $payload);
        $this->assertStringContainsString('Max Mustermann', $payload);
        $this->assertStringContainsString('DE89370400440532013000', $payload);
        $this->assertStringContainsString('EUR12.50', $payload);
    }

    public function testSepaEpcRoundTrip(): void
    {
        $sepa = new SepaEpc('Test Name', 'DE12345678901234567890', 99.99, bic: 'COBADEFFXXX');
        $payload = $sepa->toPayload();

        $this->assertTrue(SepaEpc::canDecode($payload));
        $decoded = SepaEpc::fromPayload($payload);
        $this->assertSame($payload, $decoded->toPayload());
    }

    public function testUpiPayload(): void
    {
        $upi = new Upi('merchant@upi', 'Shop Name', 100.00);
        $payload = $upi->toPayload();

        $this->assertStringStartsWith('upi://pay?', $payload);
        $this->assertStringContainsString('pa=merchant%40upi', $payload);
        $this->assertStringContainsString('am=100.00', $payload);
    }

    public function testUpiRoundTrip(): void
    {
        $upi = new Upi('test@upi', 'Test Merchant', 50.00, transactionNote: 'Order 123');
        $payload = $upi->toPayload();

        $this->assertTrue(Upi::canDecode($payload));
        $decoded = Upi::fromPayload($payload);
        $this->assertSame($payload, $decoded->toPayload());
    }

    public function testEmvQrPayload(): void
    {
        $emv = EmvQr::create('Test Merchant', 'City', 'merchant123', 10.00);
        $payload = $emv->toPayload();

        $this->assertStringStartsWith('00', $payload);
        $this->assertStringContainsString('Test Merchant', $payload);
    }

    public function testEmvQrCanDecode(): void
    {
        $emv = EmvQr::create('Shop', 'NY', 'shop123');
        $payload = $emv->toPayload();

        $this->assertTrue(EmvQr::canDecode($payload));
    }

    public function testIataBcbpPayload(): void
    {
        $bcbp = new IataBcbp(
            passengerName: 'DOE/JOHN',
            pnr: 'ABC123',
            fromCity: 'JFK',
            toCity: 'LAX',
            carrier: 'AA',
            flightNumber: '1234',
            julianDate: 120,
            compartment: 'Y',
            seatNumber: '23A ',
        );
        $payload = $bcbp->toPayload();

        $this->assertStringStartsWith('M1', $payload);
        $this->assertStringContainsString('DOE/JOHN', $payload);
        $this->assertStringContainsString('JFK', $payload);
        $this->assertStringContainsString('LAX', $payload);
    }

    public function testIataBcbpRoundTrip(): void
    {
        $bcbp = new IataBcbp(
            passengerName: 'SMITH/JANE',
            pnr: 'XYZ789',
            fromCity: 'SFO',
            toCity: 'ORD',
            carrier: 'UA',
            flightNumber: '567',
            julianDate: 200,
        );
        $payload = $bcbp->toPayload();

        $this->assertTrue(IataBcbp::canDecode($payload));
        $decoded = IataBcbp::fromPayload($payload);
        $this->assertSame($payload, $decoded->toPayload());
    }

    public function testUdiGs1Payload(): void
    {
        $udi = new Udi(
            deviceIdentifier: '00844588003288',
            lotNumber: 'LOT123',
            expirationDate: '261231',
            issuingAgency: 'GS1',
        );
        $payload = $udi->toPayload();

        $this->assertStringStartsWith('01', $payload);
        $this->assertStringContainsString('17261231', $payload);
        $this->assertStringContainsString('10LOT123', $payload);
    }

    public function testHibcPayload(): void
    {
        $hibc = new Hibc(
            lic: 'A999',
            productCode: 'ITEM1',
            unitOfMeasure: 1,
            lotNumber: 'LOT5',
            expirationDate: '122631',
        );
        $payload = $hibc->toPayload();

        $this->assertStringStartsWith('+A999', $payload);
        $this->assertStringContainsString('ITEM1', $payload);
    }

    public function testHibcCanDecode(): void
    {
        $hibc = new Hibc('B123', 'PROD', 0);
        $payload = $hibc->toPayload();

        $this->assertTrue(Hibc::canDecode($payload));
    }
}
