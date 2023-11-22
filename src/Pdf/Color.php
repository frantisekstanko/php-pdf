<?php

declare(strict_types=1);

namespace Stanko\Pdf;

final readonly class Color
{
    private function __construct(
        private int $red,
        private int $green,
        private int $blue,
    ) {
    }

    /**
     * @param int $red   0 - 255
     * @param int $green 0 - 255
     * @param int $blue  0 - 255
     */
    public static function fromRgb(int $red, int $green, int $blue): self
    {
        return new self($red, $green, $blue);
    }

    public function getRed(): int
    {
        return $this->red;
    }

    public function getGreen(): int
    {
        return $this->green;
    }

    public function getBlue(): int
    {
        return $this->blue;
    }

    public function isBlack(): bool
    {
        return $this->red === 0 && $this->green === 0 && $this->blue === 0;
    }
}
