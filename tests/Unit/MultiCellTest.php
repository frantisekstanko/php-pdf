<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\Color;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\PageRotation;
use Stanko\Pdf\PageSize;
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
        $expectedHash = 'dde1c360d57e0a3e9a0b1659ade7dea9abb423f8';

        $pdf = $this->createTestPdf();

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::NONE,
        );

        $pdf->setRightMargin(10);
        $pdf = $pdf->withAutomaticWidth()->withAutomaticHeight();

        $pdf = $pdf->withFillColor(Color::fromRgb(120, 30, 200));

        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, 0, 'C', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(0, self::TEXT_LONG_LINE, 1, 'C', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, 'T', 'C', true);
        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'R', 'C', true);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(0, self::TEXT_WITH_NEWLINES, 'B', 'C', true);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'L', 'C', true);
        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, 0, 'L', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(0, self::TEXT_LONG_LINE, 1, 'C', false);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, 'T', 'R', true);
        $pdf = $pdf->withWidth(20);
        $pdf->drawMultiCell(10, self::TEXT_LONG_LINE, 'T', 'J', true);
        $pdf = $pdf->withAutomaticWidth();
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'R', 'L', true);
        $pdf = $pdf->withWidth(20);

        $pdf = $pdf->withFillColor(Color::fromRgb(255, 255, 255));

        $pdf->drawMultiCell(0, self::TEXT_WITH_NEWLINES, 'B', 'C', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'L', 'R', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'R', 'J', true);

        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'TR', 'J', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_NEWLINES, 'LR', 'J', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, 'BR', 'J', true);

        $pdf = $pdf->withFillColor(Color::fromRgb(255, 255, 100));
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, 1, 'J', true);

        $pdf = $pdf->withFillColor(Color::fromRgb(255, 255, 255));
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, 'TL', 'J', true);
        $pdf->drawMultiCell(10, self::TEXT_WITH_MORE_NEWLINES, 'BL', 'J', true);

        $renderedPdf = $pdf->toString();

        self::assertEquals(5, $pdf->getCurrentPageNumber());
        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
