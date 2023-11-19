<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Stanko\Fpdf\Fpdf;

final class PageSizeTest extends TestCase
{
    /**
     * @testWith
     * ["a3", "b0abadeb91154766e72ef19ae4e5c2982b88d954"]
     * ["a4", "f2ffad88a7e9b5e1975b93053915a30f033474dd"]
     * ["a5", "83e2b01d456a0793af780de2f920d7c36c89ec62"]
     * ["letter", "077f65bfa850524f5409a0542e218f3967a83e39"]
     * ["legal", "5d1ed3a9fb8d3f3742943ce3dee92d469fd58c26"]
     */
    public function testPageSizes(
        string $pageSize,
        string $expectedHash,
    ): void {
        $pdf = new Fpdf('P', 'mm', $pageSize);
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
