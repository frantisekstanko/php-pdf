<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\Color;
use Stanko\Pdf\Exception\FailedToDrawCellException;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;

final class CellTest extends PdfTestCase
{
    public function testCells(): void
    {
        $expectedHash = '617529418a3a44c3c0448daf1cce7021c7c003ff';

        $pdf = $this->createTestPdf();

        $pdf = $pdf->addPage();
        $pdf = $pdf->withRightMargin(10);
        $pdf = $pdf->withAutomaticWidth()->withAutomaticHeight();
        $pdf = $pdf->drawCell('cell without border, auto size', 1, 1);
        $pdf = $pdf->withAutomaticWidth()->withHeight(40);
        $pdf = $pdf->drawCell('cell without border, auto width', 1, 2);
        $pdf = $pdf->withWidth(40)->withAutomaticHeight();
        $pdf = $pdf->drawCell('cell without border, auto height', 1, 2);
        $pdf = $pdf->withWidth(40)->withHeight(100);
        $pdf = $pdf->drawCell('cell with border', 1, 1);
        $pdf = $pdf->withWidth(50)->withHeight(10);
        $pdf = $pdf->drawCell('cell with left border', 'L');
        $pdf = $pdf->withWidth(100)->withHeight(10);
        $pdf = $pdf->drawCell('cell with right border', 'R', 0);
        $pdf = $pdf->drawCell('cell with bottom border', 'B', 1);
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->drawCell('cell with top border, right aligned text', 'T', 2, 'R');
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->drawCell('cell with top and right border, centered text', 'TR', 2, 'C');
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->withFillColor(Color::fromRgb(100, 50, 80));
        $pdf = $pdf->drawCell('cell with left and right border, filled', 'LR', 2, 'L', true);
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->drawCell('cell with top and bottom border', 'TB', 2);
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->drawCell('cell with left and bottom border', 'LB', 2);
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->drawCell('cell with left, right, bottom border', 'LRB', 2);
        $pdf = $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->drawCell('cell with right, top, left border', 'RTL', 2);
        $pdf = $pdf->drawCell(
            'cell with an external link',
            'RTL',
            2,
            'L',
            false,
            'https://github.com/frantisekstanko/php-pdf'
        );

        $link = $pdf->createLink();

        $pdf->SetLink($link, 50, 1);

        $pdf = $pdf->drawCell(
            'cell with an internal link',
            'RTL',
            2,
            'L',
            false,
            $link,
        );

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }

    public function testDrawCellThrowsExceptionWhenNoFontHasBeenSelected(): void
    {
        $this->expectException(FailedToDrawCellException::class);
        $this->expectExceptionMessage('You must call ->withFont() before calling ->drawCell()');

        (new Pdf())->drawCell('cell without border, auto size', 1, 1);
    }
}
