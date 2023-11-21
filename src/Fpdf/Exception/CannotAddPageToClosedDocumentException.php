<?php

declare(strict_types=1);

namespace Stanko\Fpdf\Exception;

use RuntimeException;

final class CannotAddPageToClosedDocumentException extends RuntimeException implements ExceptionInterface
{
}
