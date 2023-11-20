<?php

declare(strict_types=1);

namespace Stanko\Fpdf;

enum Units
{
    case POINTS;
    case MILLIMETERS;
    case CENTIMETERS;
    case INCHES;

    public function getScaleFactor(): float
    {
        return match ($this) {
            self::POINTS => 1,
            self::MILLIMETERS => 72 / 25.4,
            self::CENTIMETERS => 72 / 2.54,
            self::INCHES => 72,
        };
    }
}
