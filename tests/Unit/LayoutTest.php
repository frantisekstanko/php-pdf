<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\PageSize;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class LayoutTest extends PdfTestCase
{
    /**
     * @testWith
     * ["single", "ba24061dfff56446becf65ad0c3aefa22fde1f78"]
     * ["continuous", "b11f1d8173a7ace608fc74d1df26f85414aa20ac"]
     * ["two", "ff29e4e9d9d46a8dd6568a894b5066d6aaa9f54a"]
     * ["default", "d22a77735a2152f2aa795ceb450a42bfdc34e470"]
     */
    public function testLayout(
        string $layout,
        string $expectedHash,
    ): void {
        $pdf = new Pdf(
            PageSize::a4(),
            PageOrientation::PORTRAIT,
            Units::MILLIMETERS,
        );
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $pdf->setLayout($layout);

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
