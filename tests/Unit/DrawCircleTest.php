<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\RectangleStyle;
use Stanko\Pdf\Tests\PdfTestCase;

final class DrawCircleTest extends PdfTestCase
{
    private const CIRCLE_PATH = '198.43 558.43 m '
        . '198.43 589.74 173.04 615.12 141.73 615.12 c '
        . '110.42 615.12 85.04 589.74 85.04 558.43 c '
        . '85.04 527.11 110.42 501.73 141.73 501.73 c '
        . '173.04 501.73 198.43 527.11 198.43 558.43 c h';

    public function testDrawCircleBordered(): void
    {
        $pdf = $this->createTestPdf()
            ->withoutCompression()
            ->addPage()
            ->drawCircle(50, 100, 20, RectangleStyle::BORDERED)
        ;

        self::assertStringContainsString(self::CIRCLE_PATH . ' S', $pdf->toString());
    }

    public function testDrawCircleFilled(): void
    {
        $pdf = $this->createTestPdf()
            ->withoutCompression()
            ->addPage()
            ->drawCircle(50, 100, 20, RectangleStyle::FILLED)
        ;

        self::assertStringContainsString(self::CIRCLE_PATH . ' f', $pdf->toString());
    }

    public function testDrawCircleFilledAndBordered(): void
    {
        $pdf = $this->createTestPdf()
            ->withoutCompression()
            ->addPage()
            ->drawCircle(50, 100, 20, RectangleStyle::FILLED_AND_BORDERED)
        ;

        self::assertStringContainsString(self::CIRCLE_PATH . ' B', $pdf->toString());
    }
}
