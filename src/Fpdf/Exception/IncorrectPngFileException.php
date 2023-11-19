<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Fpdf\Exception;

use RuntimeException;
use Stanko\Fpdf\Exception\ExceptionInterface;

final class IncorrectPngFileException extends RuntimeException implements ExceptionInterface
{
}
