<?php

declare(strict_types=1);

/**
 * IATA Bar Coded Boarding Pass (BCBP) payload encoder/decoder.
 *
 * Implements IATA Resolution 792 format for PDF417/Aztec boarding passes.
 * Mandatory fields in the first leg are fixed-width; conditional items follow.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Barcode\Semantics\Transport;

use Horde\Barcode\Encoder\Pdf417Encoder;
use Horde\Barcode\Semantics\SemanticEncoderInterface;
use Horde\Barcode\Semantics\SemanticDecoderInterface;

final class IataBcbp implements SemanticEncoderInterface, SemanticDecoderInterface
{
    /**
     * @param string $passengerName Up to 20 chars, surname/first name
     * @param string $pnr Booking reference (up to 7 chars)
     * @param string $fromCity 3-letter IATA airport code
     * @param string $toCity 3-letter IATA airport code
     * @param string $carrier 2-3 char airline designator
     * @param string $flightNumber Up to 5 chars (right-padded with spaces)
     * @param int $julianDate Day of year (1-366)
     * @param string $compartment Single letter (F, J, C, Y, etc.)
     * @param string $seatNumber 4 chars (e.g., "023A")
     * @param int $checkInSequence Sequence number (up to 4 digits)
     * @param string $passengerStatus Single char (0-9, A-Z)
     */
    public function __construct(
        private readonly string $passengerName,
        private readonly string $pnr,
        private readonly string $fromCity,
        private readonly string $toCity,
        private readonly string $carrier,
        private readonly string $flightNumber,
        private readonly int $julianDate,
        private readonly string $compartment = 'Y',
        private readonly string $seatNumber = '    ',
        private readonly int $checkInSequence = 1,
        private readonly string $passengerStatus = '0',
        private readonly int $legCount = 1,
    ) {}

    public function toPayload(): string
    {
        $format = 'M';
        $legs = str_pad((string) $this->legCount, 1);
        $name = str_pad(strtoupper($this->passengerName), 20);
        $eTicket = 'E';
        $pnr = str_pad(strtoupper($this->pnr), 7);
        $from = str_pad(strtoupper($this->fromCity), 3);
        $to = str_pad(strtoupper($this->toCity), 3);
        $carrier = str_pad(strtoupper($this->carrier), 3);
        $flight = str_pad($this->flightNumber, 5);
        $julian = str_pad((string) $this->julianDate, 3, '0', STR_PAD_LEFT);
        $compartment = str_pad($this->compartment, 1);
        $seat = str_pad($this->seatNumber, 4);
        $seq = str_pad((string) $this->checkInSequence, 4, '0', STR_PAD_LEFT);
        $status = str_pad($this->passengerStatus, 1);
        $condSize = '00';

        return $format . $legs . $name . $eTicket
            . $pnr . $from . $to . $carrier . $flight
            . $julian . $compartment . $seat . $seq . $status
            . $condSize;
    }

    public function recommendedEncoder(): string
    {
        return Pdf417Encoder::class;
    }

    public static function canDecode(string $payload): bool
    {
        if (strlen($payload) < 58) {
            return false;
        }
        return $payload[0] === 'M' && ctype_digit($payload[1]);
    }

    public static function fromPayload(string $payload): static
    {
        $legs = (int) $payload[1];
        $name = trim(substr($payload, 2, 20));
        $pos = 23;
        $pnr = trim(substr($payload, $pos, 7));
        $pos += 7;
        $from = substr($payload, $pos, 3);
        $pos += 3;
        $to = substr($payload, $pos, 3);
        $pos += 3;
        $carrier = trim(substr($payload, $pos, 3));
        $pos += 3;
        $flight = trim(substr($payload, $pos, 5));
        $pos += 5;
        $julian = (int) substr($payload, $pos, 3);
        $pos += 3;
        $compartment = substr($payload, $pos, 1);
        $pos += 1;
        $seat = substr($payload, $pos, 4);
        $pos += 4;
        $seq = (int) substr($payload, $pos, 4);
        $pos += 4;
        $status = substr($payload, $pos, 1);

        return new static(
            passengerName: $name,
            pnr: $pnr,
            fromCity: $from,
            toCity: $to,
            carrier: $carrier,
            flightNumber: $flight,
            julianDate: $julian,
            compartment: $compartment,
            seatNumber: $seat,
            checkInSequence: $seq,
            passengerStatus: $status,
            legCount: $legs,
        );
    }
}
