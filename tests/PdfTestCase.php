<?php

declare(strict_types=1);

namespace Stanko\Pdf\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Stanko\Pdf\Fonts\OpenSansRegular;
use Stanko\Pdf\Pdf;

abstract class PdfTestCase extends TestCase
{
    protected static function createTestPdf(): Pdf
    {
        $pdf = (new Pdf())->createdAt(new DateTimeImmutable('2023-11-20'))
            ->loadFont(OpenSansRegular::points(12))
        ;
        $pdf->setFont(OpenSansRegular::points(12));

        return $pdf;
    }
}
