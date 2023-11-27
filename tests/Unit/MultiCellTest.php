<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\CellBorder;
use Stanko\Pdf\Color;
use Stanko\Pdf\Tests\PdfTestCase;

final class MultiCellTest extends PdfTestCase
{
    private const TEXT_LONG_LINE = 'long text 1234567890 šč+éíťýčšľúpldc+ôúäú' .
        '§+ôč+ä long text more long text more long text ____|||||| WOLOLOO' .
        'will this ever end?!';

    private const TEXT_WITH_NEWLINES = <<<'EOF'
        this is a text
        with
        newlines
        EOF;

    private const TEXT_WITH_MORE_NEWLINES = <<<'EOF'


        this is a text
        with
        even MORE newlines


        EOF;

    public function testMultiCells(): void
    {
        $expectedHash = 'd9884e8a520dcadd1624f6485256df6a1c68c642';

        $pdf = $this->createTestPdf();

        $pdf = $pdf->addPage();

        $pdf->setRightMargin(10);
        $pdf = $pdf->withAutomaticWidth()->withAutomaticHeight();

        $pdf = $pdf->withFillColor(Color::fromRgb(120, 30, 200));

        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, CellBorder::none(), 'C', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(0, self::TEXT_LONG_LINE, CellBorder::withAllSides(), 'C', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, CellBorder::top(), 'C', true);
        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, CellBorder::right(), 'C', true);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(0, self::TEXT_WITH_NEWLINES, CellBorder::bottom(), 'C', true);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, CellBorder::left(), 'C', true);
        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, CellBorder::none(), 'L', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(0, self::TEXT_LONG_LINE, CellBorder::withAllSides(), 'C', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, CellBorder::top(), 'R', true);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, CellBorder::top(), 'J', true);
        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, CellBorder::right(), 'L', true);
        $pdf = $pdf->withWidth(20);

        $pdf = $pdf->withFillColor(Color::fromRgb(255, 255, 255));

        $pdf->drawMultiCell(0, self::TEXT_WITH_NEWLINES, CellBorder::bottom(), 'C', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, CellBorder::left(), 'R', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, CellBorder::right(), 'J', true);

        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, new CellBorder(true, true, false, false), 'J', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, new CellBorder(false, true, false, true), 'J', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, new CellBorder(false, true, true, false), 'J', true);

        $pdf = $pdf->withFillColor(Color::fromRgb(255, 255, 100));
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, CellBorder::withAllSides(), 'J', true);

        $pdf = $pdf->withFillColor(Color::fromRgb(255, 255, 255));
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, new CellBorder(true, false, false, true), 'J', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, new CellBorder(false, false, true, true), 'J', true);

        $renderedPdf = $pdf->toString();

        self::assertEquals(5, $pdf->getCurrentPageNumber());

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
