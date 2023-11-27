<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\Color;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\PageRotation;
use Stanko\Pdf\PageSize;
use Stanko\Pdf\Tests\PdfTestCase;

final class CellTest extends PdfTestCase
{
    public function testCells(): void
    {
        $expectedHash = '617529418a3a44c3c0448daf1cce7021c7c003ff';

        $pdf = $this->createTestPdf();

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::NONE,
        );
        $pdf->setRightMargin(10);
        $pdf = $pdf->withAutomaticWidth()->withAutomaticHeight();
        $pdf->drawCell('cell without border, auto size', 1, 1);
        $pdf = $pdf->withAutomaticWidth()->withHeight(40);
        $pdf->drawCell('cell without border, auto width', 1, 2);
        $pdf = $pdf->withWidth(40)->withAutomaticHeight();
        $pdf->drawCell('cell without border, auto height', 1, 2);
        $pdf = $pdf->withWidth(40)->withHeight(100);
        $pdf->drawCell('cell with border', 1, 1);
        $pdf = $pdf->withWidth(50)->withHeight(10);
        $pdf->drawCell('cell with left border', 'L');
        $pdf = $pdf->withWidth(100)->withHeight(10);
        $pdf->drawCell('cell with right border', 'R', 0);
        $pdf->drawCell('cell with bottom border', 'B', 1);
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf->drawCell('cell with top border, right aligned text', 'T', 2, 'R');
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf->drawCell('cell with top and right border, centered text', 'TR', 2, 'C');
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf = $pdf->withFillColor(Color::fromRgb(100, 50, 80));
        $pdf->drawCell('cell with left and right border, filled', 'LR', 2, 'L', true);
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf->drawCell('cell with top and bottom border', 'TB', 2);
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf->drawCell('cell with left and bottom border', 'LB', 2);
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf->drawCell('cell with left, right, bottom border', 'LRB', 2);
        $pdf->drawCell('dividing cell', 0, 2);
        $pdf->drawCell('cell with right, top, left border', 'RTL', 2);
        $pdf->drawCell(
            'cell with an external link',
            'RTL',
            2,
            'L',
            false,
            'https://github.com/frantisekstanko/php-pdf'
        );

        $link = $pdf->createLink();

        $pdf->SetLink($link, 50, 1);

        $pdf->drawCell(
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
}
