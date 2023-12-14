<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\Tests\PdfTestCase;

final class PositionTest extends PdfTestCase
{
    public function testPositioning(): void
    {
        $pdf = $this->createTestPdf();
        $pdf = $pdf->addPage();

        self::assertEqualsWithDelta(10, $pdf->getX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->getY(), 0.002);

        $pdf = $pdf->atX(44)->atY(47);

        self::assertEqualsWithDelta(44, $pdf->getX(), 0.002);
        self::assertEqualsWithDelta(47, $pdf->getY(), 0.002);

        $pdf = $pdf->lowerBy(12)->rightwardBy(14);

        self::assertEqualsWithDelta(58, $pdf->getX(), 0.002);
        self::assertEqualsWithDelta(59, $pdf->getY(), 0.002);
    }
}
