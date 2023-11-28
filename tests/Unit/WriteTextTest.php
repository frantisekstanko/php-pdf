<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Exception\FailedToWriteTextException;
use Stanko\Pdf\Fonts\OpenSansBold;
use Stanko\Pdf\Fonts\OpenSansRegular;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\Tests\PdfTestCase;

final class WriteTextTest extends PdfTestCase
{
    public function testWriteText(): void
    {
        $expectedHash = '9cabaff43e9a71727e4d637b7507b515cecf89da';

        $pdf = (new Pdf())
            ->createdAt(new DateTimeImmutable('2023-11-28'))
            ->loadFont(OpenSansRegular::points(12))
            ->loadFont(OpenSansBold::points(12))
            ->addPage()
            ->withFont(OpenSansRegular::points(12))
            ->writeText(10, 'Hello world!')
            ->writeText(20, 'Hello world on another line')
            ->writeText(10, 'Yet another line of text')
            ->writeText(50, 'Another, even longer line, that will be wrapped')
            ->withFont(OpenSansBold::points(12))
            ->writeText(
                12,
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                "\n" .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                "\n" .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5'
            )
            ->writeText(
                12,
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5'
            )
        ;

        self::assertEquals(1, $pdf->getCurrentPageNumber());

        $this->storeResult($pdf);

        $actualHash = sha1($pdf->toString());

        self::assertEquals(
            $expectedHash,
            $actualHash,
        );
    }

    public function testStrangeBugWithLineHeightCalculation(): void
    {
        $expectedHash = 'd0df9e344fb76b2306185dbb16883825b16484cc';

        $pdf = (new Pdf())
            ->createdAt(new DateTimeImmutable('2023-11-28'))
            ->loadFont(OpenSansRegular::points(12))
            ->loadFont(OpenSansBold::points(12))
            ->addPage()
            ->withFont(OpenSansRegular::points(12))
            ->writeText(10, 'Hello world!')
            ->writeText(20, 'Hello world on another line')
            ->writeText(10, 'Yet another line of text')
            ->writeText(50, 'Another, even longer line, that will be wrapped')
            ->withFont(OpenSansBold::points(12))
            ->writeText(
                12,
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5' .
                'Text test text test text test text test text test 1 2 3 4 5'
            )
        ;

        self::assertEquals(2, $pdf->getCurrentPageNumber());

        $this->storeResult($pdf);

        $actualHash = sha1($pdf->toString());

        self::assertEquals(
            $expectedHash,
            $actualHash,
        );
    }

    public function testWriteTextThrowsExceptionWhenNoFontHasBeenSelected(): void
    {
        $this->expectException(FailedToWriteTextException::class);
        $this->expectExceptionMessage(
            'You must call ->withFont() before calling ->writeText()'
        );

        (new Pdf())
            ->addPage()
            ->writeText(10, 'Hello world!')
        ;
    }

    public function testWriteStringThrowsExceptionWhenStringToWriteIsEmpty(): void
    {
        $this->expectException(FailedToWriteTextException::class);
        $this->expectExceptionMessage(
            'You must provide a non-empty string to ->writeText()'
        );

        (new Pdf())
            ->addPage()
            ->loadfont(OpenSansRegular::points(12))
            ->withFont(OpenSansRegular::points(12))
            ->writeText(10, '')
        ;
    }
}
