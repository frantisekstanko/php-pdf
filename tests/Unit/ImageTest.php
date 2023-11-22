<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use DateTimeImmutable;
use Stanko\Pdf\Color;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\RectangleStyle;
use Stanko\Pdf\Tests\PdfTestCase;
use Stanko\Pdf\Units;

final class ImageTest extends PdfTestCase
{
    /**
     * @testWith
     * ["test.jpg", 15, 20, 55, 65, "c2a91f65ef6af29dda7f32f70e1bfbf5dee6718b"]
     * ["test_solid.png", 18, 25, 84, 12, "f92c82c3e8d8e61ff58169a496bb275f717297f3"]
     * ["test_transparent.png", 40, 40, 99, 110, "9c47036546205d8b1456917dac6bfa42c657fe1a"]
     * ["test.gif", 123, 3, 251, 213, "0b82023c0f796b4156803be84f8c4e558f690fdd"]
     */
    public function testImage(
        string $testImage,
        float $xPosition,
        float $yPosition,
        float $width,
        float $height,
        string $expectedHash,
    ): void {
        $pdf = new Pdf(
            PageOrientation::PORTRAIT,
            Units::MILLIMETERS,
        );
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));

        $pdf->addPage();

        $pdf->setFillColor(Color::fromRgb(50, 150, 50));

        $pdf->drawRectangle(0, 0, 400, 400, RectangleStyle::FILLED);

        $pdf->Image(
            __DIR__ . '/../../images/' . $testImage,
            $xPosition,
            $yPosition,
            $width,
            $height,
        );

        $renderedPdf = $pdf->Output('S');

        self::assertEquals($expectedHash, sha1($renderedPdf));
    }
}
