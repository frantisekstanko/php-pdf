<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Fpdf\Fpdf;
use Stanko\Fpdf\PageOrientation;
use Stanko\Fpdf\Tests\PdfTestCase;
use Stanko\Fpdf\Units;

final class ZoomTest extends PdfTestCase
{
    /**
     * @testWith
     * ["fullpage", "fb625cb7e120a10e6d13a5712f2bf6b684e5db58"]
     * ["fullwidth", "6868083c5482bfb8542f9c928cf3d86d2bce76a1"]
     * ["real", "8842c8945fc2e439c479bb7be712136122a49d01"]
     * ["default", "d22a77735a2152f2aa795ceb450a42bfdc34e470"]
     * [0.25, "a1d5b27ea444d1929f7829af2f84447c995e59cc"]
     * [0.5, "5b4f1a7e6b0aa1b6b0f7f278332e98ad892a1a90"]
     * [1.25, "5b4f1a7e6b0aa1b6b0f7f278332e98ad892a1a90"]
     */
    public function testZoom(
        float|string $zoom,
        string $expectedHash,
    ): void {
        $pdf = new Fpdf(
            PageOrientation::PORTRAIT,
            Units::MILLIMETERS,
            'a4',
        );
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $pdf->setZoom($zoom);

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
