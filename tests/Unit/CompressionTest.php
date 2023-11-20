<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Fpdf\Fpdf;
use Stanko\Fpdf\Orientation;
use Stanko\Fpdf\Tests\PdfTestCase;
use Stanko\Fpdf\Units;

final class CompressionTest extends PdfTestCase
{
    public function testEnabledCompression(): void
    {
        $expectedHash = 'd22a77735a2152f2aa795ceb450a42bfdc34e470';

        $pdf = new Fpdf(
            Orientation::PORTRAIT,
            Units::MILLIMETERS,
            'a4',
        );
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $pdf->SetCompression(true);

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }

    public function testDisabledCompression(): void
    {
        $expectedHash = 'd55fe06bed5c73c1b104094e12c66f6e2b7653df';

        $pdf = new Fpdf(
            Orientation::PORTRAIT,
            Units::MILLIMETERS,
            'a4',
        );
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $pdf->SetCompression(false);

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
