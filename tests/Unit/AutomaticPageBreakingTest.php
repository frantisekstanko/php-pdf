<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\Tests\PdfTestCase;

final class AutomaticPageBreakingTest extends PdfTestCase
{
    public function testNoPageIsAutomaticallyCreatedWhenAutomaticPageBreakingIsDisabled(): void
    {
        $pdf = $this->createTestPdf();

        $pdf = $pdf->addPage();
        self::assertEquals(1, $pdf->getCurrentPageNumber());

        $pdf = $pdf->withoutAutomaticPageBreaking();

        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->GetY(), 0.002);

        $pdf = $pdf->withWidth(10)->withHeight(100);

        for ($i = 1; $i <= 100; ++$i) {
            $pdf = $pdf->drawCell('cell ' . $i, 0, 2);
        }

        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10010, $pdf->GetY(), 0.002);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
    }

    public function testNoNewPageIsCreatedWhenMarginIsNotReached(): void
    {
        $pdf = $this->createTestPdf();
        $pdf = $pdf->addPage();
        $pdf->withAutomaticPageBreaking(80);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->GetY(), 0.002);

        $pdf = $pdf->withWidth(10)->withHeight(206);

        $pdf = $pdf->drawCell('cell ending 1 point before the break margin', 0, 2);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(216, $pdf->GetY(), 0.002);
    }

    public function testAPageIsCreatedWhenMarginIsReachedByDrawingACell(): void
    {
        $pdf = $this->createTestPdf();
        $pdf = $pdf->addPage();
        $pdf->withAutomaticPageBreaking(80);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->GetY(), 0.002);

        $pdf = $pdf->withWidth(10)->withHeight(207);

        $pdf = $pdf->drawCell('cell ending exactly at the break margin', 0, 2);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(217, $pdf->GetY(), 0.002);

        $pdf = $pdf->withWidth(10)->withHeight(10);

        $pdf = $pdf->drawCell('cell ending 1 point after the break margin', 0, 2);

        self::assertEquals(2, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(20, $pdf->GetY(), 0.002);
    }
}
