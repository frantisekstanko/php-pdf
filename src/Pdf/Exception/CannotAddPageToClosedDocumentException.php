<?php

declare(strict_types=1);

namespace Stanko\Pdf\Exception;

use RuntimeException;

final class CannotAddPageToClosedDocumentException extends RuntimeException implements ExceptionInterface
{
}
