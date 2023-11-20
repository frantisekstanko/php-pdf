<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Exception;

use RuntimeException;

final class NoPageHasBeenAddedException extends RuntimeException implements ExceptionInterface
{
}
