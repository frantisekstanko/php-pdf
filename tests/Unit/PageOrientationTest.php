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
        $expectedHash = '271a10c731cbb927c4fdf729a85430b63ece35c4';

        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('2023-12-26'));

        $pdf->addPage(PageOrientation::PORTRAIT);
        $pdf->addPage(PageOrientation::PORTRAIT);
        $pdf->addPage(PageOrientation::PORTRAIT);

        $pdf->addPage(PageOrientation::LANDSCAPE);
        $pdf->addPage(PageOrientation::LANDSCAPE);
        $pdf->addPage(PageOrientation::LANDSCAPE);

        $pdf->addPage(PageOrientation::PORTRAIT);
        $pdf->addPage(PageOrientation::PORTRAIT);
        $pdf->addPage(PageOrientation::PORTRAIT);

        $renderedPdf = $pdf->toString();

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
