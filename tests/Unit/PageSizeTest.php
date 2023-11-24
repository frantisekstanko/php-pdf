<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use RuntimeException;
use Stanko\Pdf\PageSize;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class PageSizeTest extends PdfTestCase
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
        $pdf = (new Pdf(
            Units::MILLIMETERS,
        ))->withPageSize($this->pageSizeFromString($pageSize))
            ->createdAt(new DateTimeImmutable('1999-12-26'))
        ;

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }

    private function pageSizeFromString(string $pageSize): PageSize
    {
        if ($pageSize === 'a3') {
            return PageSize::a3();
        }

        if ($pageSize === 'a4') {
            return PageSize::a4();
        }

        if ($pageSize === 'a5') {
            return PageSize::a5();
        }

        if ($pageSize === 'letter') {
            return PageSize::letter();
        }

        if ($pageSize === 'legal') {
            return PageSize::legal();
        }

        throw new RuntimeException();
    }
}
