<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\PageRotation;
use Stanko\Pdf\PageSize;
use Stanko\Pdf\Tests\PdfTestCase;

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
        $pdf = $pdf->withWidth(100)->withHeight(100);
        $pdf->drawCell('page without rotation');

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::CLOCKWISE_90_DEGREES,
        );
        $pdf->drawCell('page rotated clockwise 90 degrees');

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::UPSIDE_DOWN,
        );
        $pdf->drawCell('page upside down');

        $pdf->addPage(
            PageOrientation::PORTRAIT,
            PageSize::a4(),
            PageRotation::ANTICLOCKWISE_90_DEGREES,
        );
        $pdf->drawCell('page rotated anticlockwise 90 degrees');

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
