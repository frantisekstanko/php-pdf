<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\CellBorder;
use Stanko\Pdf\Color;
use Stanko\Pdf\Fonts\OpenSansBold;
use Stanko\Pdf\Fonts\OpenSansRegular;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\RectangleStyle;
use Stanko\Pdf\Tests\PdfTestCase;

final class FullDocumentTest extends PdfTestCase
{
    public function testFullDocument(): void
    {
        $expectedHash = '1dbd76e201c69c337a75352a00d0eabca289f3da';

        $pdf = (new Pdf())
            ->createdAt(new DateTimeImmutable('2023-11-20'))
            ->withAliasForTotalNumberOfPages('{pagesTotalTest}')
            ->loadFont(OpenSansRegular::points(12))
            ->loadFont(OpenSansBold::points(12))
            ->byAuthor('Author is the unit test <3')
            ->createdBy('Nobody')
            ->withLayout('single')
            ->withKeywords('test, unit, pdf')
            ->addPage()
        ;

        self::assertEquals(1, $pdf->getCurrentPageNumber());

        $pdf = $pdf
            ->withFont(OpenSansRegular::points(12))
            ->withFillColor(Color::fromRgb(50, 10, 5))

            ->withWidth(100)
            ->withHeight(30)
            ->drawCell('Cell test !@#* ÁČŠĎ')

            ->withWidth(90)
            ->withHeight(25)
            ->drawCell('With border', 1)

            ->onNextRow()

            ->withWidth(70)
            ->withHeight(40)
            ->drawCell('Left border', 'L', 0, 'L')

            ->withWidth(44)
            ->withHeight(32)
            ->drawCell('Right border', 'R', 1, 'C')

            ->withUnderline()
            ->drawCell('Top border, underlined text', 'T', 2, 'R')
            ->drawCell('With fill', 'B', 0, 'L', true)
        ;

        self::assertEqualsWithDelta(210.001566, $pdf->GetPageWidth(), 0.0001);
        self::assertEqualsWithDelta(297.000083, $pdf->GetPageHeight(), 0.0001);

        $pdf = $pdf
            ->insertImage(__DIR__ . '/../../images/test_solid.png')
            ->insertImage(__DIR__ . '/../../images/test_solid.png', 200, 150)
            ->insertImage(__DIR__ . '/../../images/test_solid.png', 0, 0, 10, 10)

            ->drawLine(10, 10, 90, 90)

            ->addLink(50, 50, 100, 100, 'https://nothing.io/')
        ;

        self::assertEquals(54.00125, $pdf->GetX());
        self::assertEqualsWithDelta(178.3762, $pdf->GetY(), 0.0001);

        $pdf = $pdf
            ->addPage()
            ->withFont(OpenSansBold::points(12))
            ->withoutUnderline()
            ->withWidth(100)
            ->drawMultiCell(
                10,
                "MultiCell test !@#* ÁČŠĎ\nNEW LINE",
                CellBorder::withAllSides(),
                'L',
                true
            )
        ;

        self::assertEquals(2, $pdf->getCurrentPageNumber());

        $pdf = $pdf
            ->withLineWidth(3)
            ->withDrawColor(Color::fromRgb(255, 0, 0))
            ->withFillColor(Color::fromRgb(255, 255, 0))
            ->drawRectangle(66, 77, 100, 100, RectangleStyle::BORDERED)

            ->withDrawColor(Color::fromRgb(0, 255, 0))
            ->drawRectangle(90, 90, 100, 100, RectangleStyle::FILLED)

            ->withDrawColor(Color::fromRgb(0, 0, 255))
            ->drawRectangle(120, 120, 100, 100, RectangleStyle::FILLED_AND_BORDERED)

            ->withFont(OpenSansBold::points(17))
            ->withWidth(4)
            ->withHeight(4)
            ->drawCell('TEXT')

            ->addPage()
            ->withLeftMargin(50)
            ->withRightMargin(90)
            ->withTopMargin(44)
            ->withSubject('What is this? I hope this is not Chris.')
            ->withTextColor(Color::fromRgb(0, 255, 100))
            ->withTitle('at last!')
            ->writeString(111, 122, 'Hello world!')
            ->writeText(55, 'Hello world!')
            ->writeText(55, 'Link to the world!', 'https://toTheWorld.io/')
            ->withWidth(100)->withHeight(40)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
        ;

        self::assertEquals(4, $pdf->getCurrentPageNumber());

        $pdf = $pdf
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
        ;

        self::assertEquals(5, $pdf->getCurrentPageNumber());

        $pdf = $pdf
            ->withoutAutomaticPageBreaking()
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
            ->drawCell('new line', 1, 2)
        ;

        self::assertEquals(5, $pdf->getCurrentPageNumber());

        $pdf = $pdf
            ->addPage()
            ->insertImage(__DIR__ . '/../../images/test.jpg')
            ->insertImage(__DIR__ . '/../../images/test.gif', 100, 100)
            ->drawCell('paging test, page / {pagesTotalTest}', 0, 0, 'L')
        ;

        self::assertEquals(6, $pdf->getCurrentPageNumber());

        $pdf->withAutomaticPageBreaking();

        $pdf = $pdf
            ->addPage()
            ->insertImage(__DIR__ . '/../../images/test_hires.png')
        ;

        self::assertEquals(7, $pdf->getCurrentPageNumber());

        $this->storeResult($pdf);

        $actualHash = sha1($pdf->toString());

        self::assertEquals(
            $expectedHash,
            $actualHash,
        );
    }
}
