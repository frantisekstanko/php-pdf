<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Tests\Unit;

use Stanko\Fpdf\PageOrientation;
use Stanko\Fpdf\PageRotation;
use Stanko\Fpdf\PageSize;
use Stanko\Fpdf\Tests\PdfTestCase;

final class RotationTest extends PdfTestCase
{
    public function testRotations(): void
    {
        $expectedHash = 'fadf6d3b138f91e79c1cfd8886f0c2d28e41af5a';

        $pdf = $this->createTestPdf();

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::NONE,
        );
        $pdf->Cell(100, 100, 'page without rotation');

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::CLOCKWISE_90_DEGREES,
        );
        $pdf->Cell(100, 100, 'page rotated clockwise 90 degrees');

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::UPSIDE_DOWN,
        );
        $pdf->Cell(100, 100, 'page upside down');

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::ANTICLOCKWISE_90_DEGREES,
        );
        $pdf->Cell(100, 100, 'page rotated anticlockwise 90 degrees');

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
