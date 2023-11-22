<?php

declare(strict_types=1);

namespace Stanko\Pdf;

enum PageRotation
{
    case NONE;
    case CLOCKWISE_90_DEGREES;
    case UPSIDE_DOWN;
    case ANTICLOCKWISE_90_DEGREES;

    public function toInteger(): int
    {
        return match ($this) {
            self::NONE => 0,
            self::CLOCKWISE_90_DEGREES => 90,
            self::UPSIDE_DOWN => 180,
            self::ANTICLOCKWISE_90_DEGREES => 270,
        };
    }
}
