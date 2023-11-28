<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Exception\FailedToWriteStringException;
use Stanko\Pdf\Fonts\OpenSansBold;
use Stanko\Pdf\Fonts\OpenSansRegular;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;

final class WriteStringTest extends PdfTestCase
{
    public function testWriteString(): void
    {
        $expectedHash = '90d5c834729e09a799457808064f0e0065dbc14d';

        $pdf = (new Pdf())
            ->createdAt(new DateTimeImmutable('2023-11-28'))
            ->loadFont(OpenSansRegular::points(12))
            ->loadFont(OpenSansBold::points(12))
            ->addPage()
            ->withFont(OpenSansRegular::points(12))
            ->writeString(10, 10, 'Hello world!')
            ->writeString(10, 15, 'Hello world on another line!')
            ->writeString(200, 15, 'Outside of right bound')
            ->withFont(OpenSansBold::points(18))
            ->writeString(50, 298, 'Outside of bottom bound')
        ;

        self::assertEquals(1, $pdf->getCurrentPageNumber());

        $this->storeResult($pdf);

        $actualHash = sha1($pdf->toString());

        self::assertEquals(
            $expectedHash,
            $actualHash,
        );
    }

    public function testWriteStringThrowsExceptionWhenNoFontHasBeenSelected(): void
    {
        $this->expectException(FailedToWriteStringException::class);
        $this->expectExceptionMessage(
            'You must call ->withFont() before calling ->writeString()'
        );

        (new Pdf())
            ->addPage()
            ->writeString(10, 10, 'Hello world!')
        ;
    }

    public function testWriteStringThrowsExceptionWhenStringToWriteIsEmpty(): void
    {
        $this->expectException(FailedToWriteStringException::class);
        $this->expectExceptionMessage(
            'You must provide a non-empty string to ->writeString()'
        );

        (new Pdf())
            ->addPage()
            ->loadfont(OpenSansRegular::points(12))
            ->withFont(OpenSansRegular::points(12))
            ->writeString(10, 10, '')
        ;
    }
}
