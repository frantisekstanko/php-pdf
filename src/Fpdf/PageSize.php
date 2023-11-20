<?php

declare(strict_types=1);

namespace Stanko\Fpdf;

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

    public function getWidth(float $scaleFactor): float
    {
        return $this->width / $scaleFactor;
    }

    public function getHeight(float $scaleFactor): float
    {
        return $this->height / $scaleFactor;
    }
}
