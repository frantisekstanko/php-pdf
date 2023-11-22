<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Stanko\Fpdf\Fonts\OpenSansRegular;
use Stanko\Fpdf\PageSize;
use Stanko\Fpdf\Pdf;

abstract class PdfTestCase extends TestCase
{
    protected static function createTestPdf(): Pdf
    {
        $pdf = new Pdf(PageSize::a4());
        $pdf->setCreatedAt(new DateTimeImmutable('2023-11-20'));
        $pdf->addFont(OpenSansRegular::points(12));
        $pdf->setFont(OpenSansRegular::points(12));

        return $pdf;
    }
}
