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
        $expectedHash = '17b0ebb9945c8a97325b5e963f200920a092a3d3';

        $pdf = $this->createTestPdf();

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::NONE,
        );
        $pdf->setRightMargin(10);
        $pdf->drawCell(0, 0, 'cell without border, auto size', 1, 1);
        $pdf->drawCell(0, 40, 'cell without border, auto width', 1, 2);
        $pdf->drawCell(40, 0, 'cell without border, auto height', 1, 2);
        $pdf->drawCell(40, 100, 'cell with border', 1, 1);
        $pdf->drawCell(50, 10, 'cell with left border', 'L');
        $pdf->drawCell(100, 10, 'cell with right border', 'R', 0);
        $pdf->drawCell(100, 10, 'cell with bottom border', 'B', 1);
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf->drawCell(100, 10, 'cell with top border, right aligned text', 'T', 2, 'R');
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf->drawCell(100, 10, 'cell with top and right border, centered text', 'TR', 2, 'C');
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf = $pdf->withFillColor(Color::fromRgb(100, 50, 80));
        $pdf->drawCell(100, 10, 'cell with left and right border, filled', 'LR', 2, 'L', true);
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf->drawCell(100, 10, 'cell with top and bottom border', 'TB', 2);
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf->drawCell(100, 10, 'cell with left and bottom border', 'LB', 2);
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf->drawCell(100, 10, 'cell with left, right, bottom border', 'LRB', 2);
        $pdf->drawCell(100, 10, 'dividing cell', 0, 2);
        $pdf->drawCell(100, 10, 'cell with right, top, left border', 'RTL', 2);
        $pdf->drawCell(
            100,
            10,
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
            100,
            10,
            'cell with an internal link',
            'RTL',
            2,
            'L',
            false,
            $link,
        );

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
