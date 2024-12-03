<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Color;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\RectangleStyle;
use Stanko\Pdf\Tests\PdfTestCase;

final class ImageTest extends PdfTestCase
{
    /**
     * @testWith
     * ["test.jpg", 15, 20, 55, 65, "c7ce9da7dae2da60b02151f7f2723bd12b8f657b"]
     * ["test_solid.png", 18, 25, 84, 12, "bcd9962c9d62b4ce4893b562a9e3b7896775be90"]
     * ["test_transparent.png", 40, 40, 99, 110, "a977580cb0a571ccb2d72ff8711fbaab32328164"]
     * ["test.gif", 123, 3, 251, 213, "385104f3adabd7cb9f1fff75f00669c3c06e7207"]
     * ["test_indexed.png", 22, 33, 44, 120, "6fe556afd72b77c2dd748c41a01ae40899a6b72c"]
     */
    public function testImage(
        string $testImage,
        float $xPosition,
        float $yPosition,
        float $width,
        float $height,
        string $expectedHash,
    ): void {
        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('1999-12-26'));

        $pdf = $pdf->addPage();

        $pdf = $pdf->withFillColor(Color::fromRgb(50, 150, 50));

        $pdf = $pdf->drawRectangle(0, 0, 400, 400, RectangleStyle::FILLED);

        $pdf = $pdf->insertImage(
            __DIR__ . '/../../images/' . $testImage,
            $xPosition,
            $yPosition,
            $width,
            $height,
        );

        $renderedPdf = $pdf->toString();

        $this->storeResult($pdf);

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
