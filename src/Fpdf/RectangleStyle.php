<?php

declare(strict_types=1);

namespace Stanko\Fpdf;

enum RectangleStyle
{
    case BORDERED;
    case FILLED;
    case FILLED_AND_BORDERED;
}
