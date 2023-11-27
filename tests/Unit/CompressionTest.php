<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;

final class CompressionTest extends PdfTestCase
{
    public function testEnabledCompression(): void
    {
        $expectedHash = '51845de2df27c8eaec2d75682834c4fa1b65be70';

        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('1999-12-26'));

        $pdf->enableCompression();

        $renderedPdf = $pdf->toString();

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }

    public function testDisabledCompression(): void
    {
        $expectedHash = 'cf66b8590b2b18c26cdd33b29c20d5c38e047a97';

        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('1999-12-26'));

        $pdf->disableCompression();

        $renderedPdf = $pdf->toString();

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
