<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Fpdf\Exception;

use RuntimeException;
use Stanko\Fpdf\Exception\ExceptionInterface;

final class UnknownCompressionMethodException extends RuntimeException implements ExceptionInterface
{
}
