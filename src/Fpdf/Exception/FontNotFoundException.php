<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Exception;

use RuntimeException;

final class FontNotFoundException extends RuntimeException implements ExceptionInterface
{
}
