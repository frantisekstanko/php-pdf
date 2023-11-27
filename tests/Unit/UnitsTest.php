<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use RuntimeException;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\RectangleStyle;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class UnitsTest extends PdfTestCase
{
    /**
     * @testWith
     * ["millimeters", "5b52d8820106e6aa8538658e99f94d96850a7351"]
     * ["centimeters", "f71b5fdea11d0ab962ac5f11e0f2e9a7eec488b9"]
     * ["inches", "71db891a602132024edb13ab412e45afe607364f"]
     * ["points", "e5b69a4f145768e6f362efeb8c31a5128a0b5ec8"]
     */
    public function testUnit(
        string $units,
        string $expectedHash,
    ): void {
        $pdf = (new Pdf())
            ->createdAt(new DateTimeImmutable('2023-11-24'))
            ->inUnits($this->getUnitsFromString($units))
            ->addPage()
            ->drawRectangle(0, 0, 7, 7, RectangleStyle::FILLED)
        ;

        $this->storeResult($pdf);

        $actualHash = sha1($pdf->toString());

        self::assertEquals($expectedHash, $actualHash);
    }

    private function getUnitsFromString(string $units): Units
    {
        return match ($units) {
            'millimeters' => Units::MILLIMETERS,
            'centimeters' => Units::CENTIMETERS,
            'inches' => Units::INCHES,
            'points' => Units::POINTS,
            default => throw new RuntimeException(),
        };
    }
}
