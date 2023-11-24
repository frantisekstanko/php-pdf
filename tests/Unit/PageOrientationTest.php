<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use RuntimeException;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class PageOrientationTest extends PdfTestCase
{
    /**
     * @testWith
     * ["portrait", "9a22fc8f732cc93942145f648d8aca238d9c4740"]
     * ["landscape", "064aafe5ea0d278179924bc5921fcfc31a1b8743"]
     */
    public function testOrientation(
        string $orientation,
        string $expectedHash,
    ): void {
        $pdf = (new Pdf(
            $this->pageOrientationFromString($orientation),
            Units::MILLIMETERS,
        ))->createdAt(new DateTimeImmutable('2023-12-26'));

        $pdf->addPage();
        $pdf->addPage();
        $pdf->addPage();

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }

    private function pageOrientationFromString(string $orientation): PageOrientation
    {
        return match ($orientation) {
            'portrait' => PageOrientation::PORTRAIT,
            'landscape' => PageOrientation::LANDSCAPE,
            default => throw new RuntimeException(),
        };
    }
}
