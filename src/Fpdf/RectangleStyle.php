<?php

declare(strict_types=1);

namespace Stanko\Fpdf;

enum RectangleStyle
{
    case BORDERED;
    case FILLED;
    case FILLED_AND_BORDERED;

    public function toPdfOperation(): string
    {
        return match ($this) {
            self::BORDERED => 'S',
            self::FILLED => 'f',
            self::FILLED_AND_BORDERED => 'B',
        };
    }
}
