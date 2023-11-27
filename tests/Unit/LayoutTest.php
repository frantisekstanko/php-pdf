<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;

final class LayoutTest extends PdfTestCase
{
    /**
     * @testWith
     * ["single", "8495ad4d0fadc78ae4bfe5d7d528e06ad4401b93"]
     * ["continuous", "cd494a275f8b8a63d494368821fbf3e411801324"]
     * ["two", "bf2f223eb863d01d47015d9b5a41855a2a0b92ed"]
     * ["default", "51845de2df27c8eaec2d75682834c4fa1b65be70"]
     */
    public function testLayout(
        string $layout,
        string $expectedHash,
    ): void {
        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('1999-12-26'));

        $pdf->setLayout($layout);

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
