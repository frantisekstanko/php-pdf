<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;

final class PageOrientationTest extends PdfTestCase
{
    public function testOrientation(): void
    {
        $expectedHash = 'fc351f90edd41a4c8446c5bb8ec682328427b2f9';

        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('2023-12-26'));

        $pdf = $pdf->withPageOrientation(PageOrientation::PORTRAIT);

        $pdf->addPage();
        $pdf->addPage();
        $pdf->addPage();

        $pdf = $pdf->withPageOrientation(PageOrientation::LANDSCAPE);

        $pdf->addPage();
        $pdf->addPage();
        $pdf->addPage();

        $pdf = $pdf->withPageOrientation(PageOrientation::PORTRAIT);

        $pdf->addPage();
        $pdf->addPage();
        $pdf->addPage();

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
