<?php

declare(strict_types=1);

namespace Stanko\Pdf;

enum DocumentState
{
    case NOT_INITIALIZED;
    case PAGE_STARTED;
    case PAGE_ENDED;
    case CLOSED;
}
