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
        $expectedHash = 'a779b9c06ae65981e35334baaec4fd8e31591c7f';

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

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
