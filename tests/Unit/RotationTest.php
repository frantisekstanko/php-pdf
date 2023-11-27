<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\PageRotation;
use Stanko\Pdf\Tests\PdfTestCase;

final class RotationTest extends PdfTestCase
{
    public function testRotations(): void
    {
        $expectedHash = 'a779b9c06ae65981e35334baaec4fd8e31591c7f';

        $pdf = $this->createTestPdf();

        $pdf = $pdf->withPageRotation(PageRotation::NONE);
        $pdf = $pdf->addPage();
        $pdf = $pdf->withWidth(100)->withHeight(100);
        $pdf = $pdf->drawCell('page without rotation');

        $pdf = $pdf->withPageRotation(PageRotation::CLOCKWISE_90_DEGREES);
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawCell('page rotated clockwise 90 degrees');

        $pdf = $pdf->withPageRotation(PageRotation::UPSIDE_DOWN);
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawCell('page upside down');

        $pdf = $pdf->withPageRotation(PageRotation::ANTICLOCKWISE_90_DEGREES);
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawCell('page rotated anticlockwise 90 degrees');

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
