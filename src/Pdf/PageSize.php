<?php

declare(strict_types=1);

namespace Stanko\Pdf;

final readonly class PageSize
{
    public function __construct(
        private float $width,
        private float $height,
    ) {
    }

    public static function a3(): self
    {
        return new self(841.89, 1190.55);
    }

    public static function a4(): self
    {
        return new self(595.28, 841.89);
    }

    public static function a5(): self
    {
        return new self(420.94, 595.28);
    }

    public static function letter(): self
    {
        return new self(612, 792);
    }

    public static function legal(): self
    {
        return new self(612, 1008);
    }

    public static function custom(
        float $width,
        float $height,
    ): self {
        return new self($width, $height);
    }

    public function getWidth(Units $inUnits): float
    {
        return $this->width / $inUnits->getScaleFactor();
    }

    public function getHeight(Units $inUnits): float
    {
        return $this->height / $inUnits->getScaleFactor();
    }
}
