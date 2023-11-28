<?php

declare(strict_types=1);

namespace Stanko\Pdf\Fonts;

use Stanko\Pdf\FontInterface;

final readonly class OpenSansSemiCondensedBold implements FontInterface
{
    private const FONT_PATH = __DIR__ . '/../../../fonts/OpenSans/OpenSans_SemiCondensed-Bold.ttf';

    private function __construct(
        private float $fontSizeInPoints,
    ) {
    }

    public function getFontFilePath(): string
    {
        return self::FONT_PATH;
    }

    public static function points(float $fontSizeInPoints): self
    {
        return new self($fontSizeInPoints);
    }

    public function getFontSize(): float
    {
        return $this->fontSizeInPoints;
    }
}
