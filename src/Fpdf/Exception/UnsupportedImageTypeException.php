<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Exception;

use RuntimeException;

final class UnsupportedImageTypeException extends RuntimeException implements ExceptionInterface
{
}
