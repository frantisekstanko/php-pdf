<?php

declare(strict_types=1);

namespace Stanko\Fpdf;

enum DocumentState
{
    case NOT_INITIALIZED;
    case PAGE_STARTED;
    case PAGE_ENDED;
    case CLOSED;
}
