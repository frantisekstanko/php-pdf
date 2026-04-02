<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests\Unit;

use Stanko\Pdf\Color;
use Stanko\Pdf\Tests\PdfTestCase;

final class ColorTest extends PdfTestCase
{
    public function testDrawColorSwitchesToBlackAndBackAcrossMultiplePages(): void
    {
        $grey = Color::fromRgb(155, 155, 155);
        $red = Color::fromRgb(200, 50, 50);
        $black = Color::fromRgb(0, 0, 0);

        $pdf = $this->createTestPdf()
            ->withoutCompression()
            ->addPage()
            ->withDrawColor($grey)
            ->drawLine(10, 10, 50, 10)
            ->withDrawColor($black)
            ->drawLine(10, 20, 50, 20)
            ->withDrawColor($red)
            ->drawLine(10, 30, 50, 30)
            ->addPage()
            ->withDrawColor($black)
            ->drawLine(10, 10, 50, 10)
            ->withDrawColor($grey)
            ->drawLine(10, 20, 50, 20)
            ->withDrawColor($black)
            ->drawLine(10, 30, 50, 30)
        ;

        $output = $pdf->toString();

        $greyCommand = '0.608 0.608 0.608 RG';
        $redCommand = '0.784 0.196 0.196 RG';
        $blackCommand = '0.000 G';

        self::assertStringContainsString($greyCommand, $output);
        self::assertStringContainsString($redCommand, $output);

        $firstBlackPosition = strpos($output, $blackCommand);
        $lastBlackPosition = strrpos($output, $blackCommand);

        self::assertNotFalse($firstBlackPosition);
        self::assertNotFalse($lastBlackPosition);
        self::assertNotSame($firstBlackPosition, $lastBlackPosition);

        self::assertLessThan(
            $firstBlackPosition,
            strpos($output, $greyCommand),
        );

        self::assertGreaterThan(
            $firstBlackPosition,
            strpos($output, $redCommand),
        );
    }

    public function testFillColorSwitchesToBlackAndBackAcrossMultiplePages(): void
    {
        $blue = Color::fromRgb(50, 100, 200);
        $green = Color::fromRgb(50, 180, 80);
        $black = Color::fromRgb(0, 0, 0);

        $pdf = $this->createTestPdf()
            ->withoutCompression()
            ->addPage()
            ->withFillColor($blue)
            ->withHeight(10)->withWidth(40)
            ->drawCell('blue fill', 0, 1, 'L', true)
            ->withFillColor($black)
            ->drawCell('black fill', 0, 1, 'L', true)
            ->withFillColor($green)
            ->drawCell('green fill', 0, 1, 'L', true)
            ->addPage()
            ->withFillColor($black)
            ->drawCell('black fill', 0, 1, 'L', true)
            ->withFillColor($blue)
            ->drawCell('blue fill', 0, 1, 'L', true)
            ->withFillColor($black)
            ->drawCell('black fill', 0, 1, 'L', true)
        ;

        $output = $pdf->toString();

        $blueCommand = '0.196 0.392 0.784 rg';
        $greenCommand = '0.196 0.706 0.314 rg';
        $blackCommand = '0.000 g';

        self::assertStringContainsString($blueCommand, $output);
        self::assertStringContainsString($greenCommand, $output);

        $firstBlackPosition = strpos($output, $blackCommand);
        $lastBlackPosition = strrpos($output, $blackCommand);

        self::assertNotFalse($firstBlackPosition);
        self::assertNotFalse($lastBlackPosition);
        self::assertNotSame($firstBlackPosition, $lastBlackPosition);

        self::assertLessThan(
            $firstBlackPosition,
            strpos($output, $blueCommand),
        );

        self::assertGreaterThan(
            $firstBlackPosition,
            strpos($output, $greenCommand),
        );
    }
}
