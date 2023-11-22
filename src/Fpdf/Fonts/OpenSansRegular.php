<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Fonts;

use Stanko\Fpdf\FontInterface;

final readonly class OpenSansRegular implements FontInterface
{
    private const FONT_PATH = __DIR__ . '/../../../fonts/OpenSans/OpenSans-Regular.ttf';

    private function __construct(
        private float $fontSizeInPoints,
    ) {
    }

    public function getTtfFilePath(): string
    {
        return self::FONT_PATH;
    }

    public static function points(float $fontSizeInPoints): self
    {
        return new self($fontSizeInPoints);
    }

    public function getSizeInPoints(): float
    {
        return $this->fontSizeInPoints;
    }
}
