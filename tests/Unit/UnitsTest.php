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
     * ["millimeters", "d7f58a6963b272afce0382062754d26659803768"]
     * ["centimeters", "057de31cf84fd31a70856b4849593d93b8fd65d3"]
     * ["inches", "acaf35c5a4371bf29295db10be9391d5d9d67788"]
     * ["points", "35b1450435469e9692a148ec51cea46d602a5f43"]
     */
    public function testUnit(
        string $units,
        string $expectedHash,
    ): void {
        $pdf = (new Pdf(
            $this->getUnitsFromString($units),
        ))->createdAt(new DateTimeImmutable('2023-11-24'));

        $pdf->addPage();

        $pdf->drawRectangle(0, 0, 7, 7, RectangleStyle::FILLED);

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
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
