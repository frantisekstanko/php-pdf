<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Stanko\Fpdf\Fpdf;

final class FullDocumentTest extends TestCase
{
    public function testFullDocument(): void
    {
        $pdf = new Fpdf();
        $pdf->setFontPath(__DIR__ . '/../../fonts/OpenSans/');

        $pdf->AddFont(
            'Open Sans',
            '',
            'OpenSans-Regular.ttf',
            true,
        );
        $pdf->AddFont(
            'Open Sans Bold',
            '',
            'OpenSans-Bold.ttf',
            true,
        );
        $pdf->SetFont('Open Sans', '', 12);
        $pdf->SetFillColor(50, 10, 5);
        $pdf->AddPage();
        self::assertEquals(1, $pdf->PageNo());
        $pdf->setCreatedAt(new DateTimeImmutable('1999-12-26'));
        $pdf->Cell(100, 30, 'Cell test !@#* ÁČŠĎ');
        $pdf->Cell(90, 25, 'With border', 1);
        $pdf->Ln();
        $pdf->Cell(70, 40, 'Left border', 'L', 0, 'L');
        $pdf->Cell(44, 32, 'Right border', 'R', 1, 'C');
        $pdf->Cell(44, 32, 'Top border', 'T', 2, 'R');
        $pdf->Cell(44, 32, 'With fill', 'B', 0, 'L', true);

        $pdf->AliasNbPages('{pagesTotalTest}');

        self::assertEqualsWithDelta(210.001566, $pdf->GetPageWidth(), 0.0001);
        self::assertEqualsWithDelta(297.000083, $pdf->GetPageHeight(), 0.0001);

        $pdf->Image(__DIR__ . '/../../images/test.png');
        $pdf->Image(__DIR__ . '/../../images/test.png', 200, 150);
        $pdf->Image(__DIR__ . '/../../images/test.png', 0, 0, 10, 10);

        $pdf->Line(10, 10, 90, 90);

        $pdf->Link(50, 50, 100, 100, 'https://nothing.io/');

        self::assertEquals(54.00125, $pdf->GetX());
        self::assertEqualsWithDelta(178.3762, $pdf->GetY(), 0.0001);

        $pdf->AddPage();

        $pdf->SetFont('Open Sans Bold', '', 12);

        $pdf->MultiCell(100, 10, "MultiCell test !@#* ÁČŠĎ\nNEW LINE", 1, 'L', true);

        self::assertEquals(2, $pdf->PageNo());

        $pdf->SetLineWidth(3);
        $pdf->SetDrawColor(255, 0, 0);
        $pdf->SetFillColor(255, 255, 0);
        $pdf->Rect(66, 77, 100, 100);
        $pdf->SetDrawColor(0, 255, 0);
        $pdf->Rect(90, 90, 100, 100, 'F');
        $pdf->SetDrawColor(0, 0, 255);
        $pdf->Rect(120, 120, 100, 100, 'DF');

        $pdf->SetAuthor('Unit test', true);
        $pdf->SetCreator('Nobody', true);
        $pdf->SetDisplayMode('fullpage', 'single');

        $pdf->SetFontSize(17);

        $pdf->Cell(4, 4, 'TEXT');
        $pdf->SetKeywords('test, unit, pdf', true);

        $pdf->AddPage();

        $pdf->SetLeftMargin(50);
        $pdf->SetRightMargin(90);
        $pdf->SetTopMargin(44);
        $pdf->SetSubject('What is this? I hope this is not Chris.', true);
        $pdf->SetTextColor(0, 255, 100);
        $pdf->SetTitle('at last!', true);
        $pdf->Text(111, 122, 'Hello world!');
        $pdf->Write(55, 'Hello world!');
        $pdf->Write(55, 'Link to the world!', 'https://toTheWorld.io/');

        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);

        self::assertEquals(4, $pdf->PageNo());

        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);

        self::assertEquals(5, $pdf->PageNo());

        $pdf->SetAutoPageBreak(false);

        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);
        $pdf->Cell(100, 40, 'new line', 1, 2);

        self::assertEquals(5, $pdf->PageNo());

        $renderedPdf = $pdf->Output('S');

        self::assertEquals(
            '47a4d6d1443512ad6bee709d719dc42aba9da3ae',
            sha1($renderedPdf)
        );
    }
}
