<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class CompressionTest extends PdfTestCase
{
    public function testEnabledCompression(): void
    {
        $expectedHash = 'd22a77735a2152f2aa795ceb450a42bfdc34e470';

        $pdf = (new Pdf(
            PageOrientation::PORTRAIT,
            Units::MILLIMETERS,
        ))->createdAt(new DateTimeImmutable('1999-12-26'));

        $pdf->enableCompression();

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }

    public function testDisabledCompression(): void
    {
        $expectedHash = 'd55fe06bed5c73c1b104094e12c66f6e2b7653df';

        $pdf = (new Pdf(
            PageOrientation::PORTRAIT,
            Units::MILLIMETERS,
        ))->createdAt(new DateTimeImmutable('1999-12-26'));

        $pdf->disableCompression();

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
