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

    public function testMultiCells(): void
    {
        $expectedHash = 'dfe67a6cd6210d4b49ec0ef1298eaac4493d311d';

        $pdf = $this->createTestPdf();

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::NONE,
        );

        $pdf->setRightMargin(10);
        $pdf = $pdf->withAutomaticWidth()->withAutomaticHeight();

        $pdf = $pdf->withFillColor(Color::fromRgb(120, 30, 200));

        $pdf->drawMultiCell(0, 10, self::TEXT_LONG_LINE, 0, 'C', false);
        $pdf->drawMultiCell(20, 0, self::TEXT_LONG_LINE, 1, 'C', false);
        $pdf->drawMultiCell(20, 10, self::TEXT_LONG_LINE, 'T', 'C', true);
        $pdf->drawMultiCell(0, 10, self::TEXT_WITH_NEWLINES, 'R', 'C', true);
        $pdf->drawMultiCell(20, 0, self::TEXT_WITH_NEWLINES, 'B', 'C', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'L', 'C', true);
        $pdf->drawMultiCell(0, 10, self::TEXT_LONG_LINE, 0, 'L', false);
        $pdf->drawMultiCell(20, 0, self::TEXT_LONG_LINE, 1, 'C', false);
        $pdf->drawMultiCell(20, 10, self::TEXT_LONG_LINE, 'T', 'R', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_LONG_LINE, 'T', 'J', true);
        $pdf->drawMultiCell(0, 10, self::TEXT_WITH_NEWLINES, 'R', 'L', true);
        $pdf->drawMultiCell(20, 0, self::TEXT_WITH_NEWLINES, 'B', 'C', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'L', 'R', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'J', 'R', true);

        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'J', 'TR', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'J', 'LR', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'J', 'BR', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'J', 'TL', true);
        $pdf->drawMultiCell(20, 10, self::TEXT_WITH_NEWLINES, 'J', 'BL', true);

        $renderedPdf = $pdf->toString();

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
