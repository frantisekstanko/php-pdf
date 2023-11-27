<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\PageSize;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\RectangleStyle;
use Stanko\Pdf\Tests\PdfTestCase;

final class PageSizeTest extends PdfTestCase
{
    public function testPageSizes(): void
    {
        $expectedHash = '83aedc3247b221b0184a02eecebd5d9f4efb9231';

        $pdf = (new Pdf())->createdAt(
            new DateTimeImmutable('1999-12-26')
        );

        $pdf = $pdf->withPageSize(PageSize::a3());
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawRectangle(10, 10, 100, 100, RectangleStyle::FILLED);

        $pdf = $pdf->withPageSize(PageSize::a4());
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawRectangle(10, 10, 100, 100, RectangleStyle::FILLED);

        $pdf = $pdf->withPageSize(PageSize::a5());
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawRectangle(10, 10, 100, 100, RectangleStyle::FILLED);

        $pdf = $pdf->withPageSize(PageSize::letter());
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawRectangle(10, 10, 100, 100, RectangleStyle::FILLED);

        $pdf = $pdf->withPageSize(PageSize::legal());
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawRectangle(10, 10, 100, 100, RectangleStyle::FILLED);

        $pdf = $pdf->withPageSize(PageSize::custom(400, 400));
        $pdf = $pdf->addPage();
        $pdf = $pdf->drawRectangle(10, 10, 100, 100, RectangleStyle::FILLED);

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
