<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Stanko\Fpdf\Fpdf;
use Stanko\Fpdf\Orientation;
use Stanko\Fpdf\Units;

final class PageSizeTest extends TestCase
{
    /**
     * @testWith
     * ["a3", "e455315deb50df5f7b6c6521ea50ccff03873756"]
     * ["a4", "d22a77735a2152f2aa795ceb450a42bfdc34e470"]
     * ["a5", "6a1b003c662a24e7f9d5a6cdc989e34d0ba6c0c8"]
     * ["letter", "967923230e4521d5bcd76554eefaa85f35ebebb0"]
     * ["legal", "324ca2db375d842c9ded265efe86c97a9f0d4fc8"]
     */
    public function testPageSizes(
        string $pageSize,
        string $expectedHash,
    ): void {
        $pdf = new Fpdf(
            Orientation::PORTRAIT,
            Units::MILLIMETERS,
            $pageSize
        );
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
