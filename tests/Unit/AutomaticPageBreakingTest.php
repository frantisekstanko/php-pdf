<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Fonts\OpenSansRegular;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class AutomaticPageBreakingTest extends PdfTestCase
{
    public function testNoPageIsAutomaticallyCreatedWhenAutomaticPageBreakingIsDisabled(): void
    {
        $pdf = $this->createTestPdf();

        $pdf->addPage();
        self::assertEquals(1, $pdf->getCurrentPageNumber());

        $pdf->disableAutomaticPageBreaking();

        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->GetY(), 0.002);

        for ($i = 1; $i <= 100; ++$i) {
            $pdf->Cell(10, 100, 'cell ' . $i, 0, 2);
        }

        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10010, $pdf->GetY(), 0.002);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
    }

    public function testNoNewPageIsCreatedWhenMarginIsNotReached(): void
    {
        $pdf = $this->createPdf();
        $pdf->addPage();
        $pdf->enableAutomaticPageBreaking(80);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->GetY(), 0.002);

        $pdf->Cell(10, 206, 'cell ending 1 point before the break margin', 0, 2);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(216, $pdf->GetY(), 0.002);
    }

    public function testAPageIsCreatedWhenMarginIsReachedByDrawingACell(): void
    {
        $pdf = $this->createPdf();
        $pdf->addPage();
        $pdf->enableAutomaticPageBreaking(80);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(10, $pdf->GetY(), 0.002);

        $pdf->Cell(10, 207, 'cell ending exactly at the break margin', 0, 2);

        self::assertEquals(1, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(217, $pdf->GetY(), 0.002);

        $pdf->Cell(10, 10, 'cell ending 1 point after the break margin', 0, 2);

        self::assertEquals(2, $pdf->getCurrentPageNumber());
        self::assertEqualsWithDelta(10, $pdf->GetX(), 0.002);
        self::assertEqualsWithDelta(20, $pdf->GetY(), 0.002);
    }

    private function createPdf(): Pdf
    {
        $pdf = (new Pdf(
            PageOrientation::PORTRAIT,
            Units::MILLIMETERS,
        ))->loadFont(OpenSansRegular::points(12));

        $pdf->createdAt(new DateTimeImmutable('2023-11-20'));
        $pdf->setFont(OpenSansRegular::points(12));

        return $pdf;
    }
}
