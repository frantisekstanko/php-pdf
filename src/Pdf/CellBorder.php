<?php

declare(strict_types=1);

namespace Stanko\Pdf;

final readonly class CellBorder
{
    public function __construct(
        private bool $top,
        private bool $right,
        private bool $bottom,
        private bool $left,
    ) {
    }

    public static function withAllSides(): self
    {
        return new self(true, true, true, true);
    }

    public static function none(): self
    {
        return new self(false, false, false, false);
    }

    public static function top(): self
    {
        return new self(true, false, false, false);
    }

    public static function right(): self
    {
        return new self(false, true, false, false);
    }

    public static function bottom(): self
    {
        return new self(false, false, true, false);
    }

    public static function left(): self
    {
        return new self(false, false, false, true);
    }

    public function hasAllSides(): bool
    {
        return $this->top && $this->right && $this->bottom && $this->left;
    }

    public function hasTop(): bool
    {
        return $this->top;
    }

    public function hasRight(): bool
    {
        return $this->right;
    }

    public function hasBottom(): bool
    {
        return $this->bottom;
    }

    public function hasLeft(): bool
    {
        return $this->left;
    }

    public function hasAnySide(): bool
    {
        return $this->top || $this->right || $this->bottom || $this->left;
    }

    public function withBottomBorder(): self
    {
        return new self($this->top, $this->right, true, $this->left);
    }
}
