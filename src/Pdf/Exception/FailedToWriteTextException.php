<?php

declare(strict_types=1);

namespace Stanko\Pdf\Exception;

use RuntimeException;

final class FailedToWriteTextException extends RuntimeException implements ExceptionInterface
{
    public static function becauseNoFontHasBeenSelected(): self
    {
        return new self('You must call ->withFont() before calling ->writeText()');
    }

    public static function becauseStringToWriteIsEmpty(): self
    {
        return new self('You must provide a non-empty string to ->writeText()');
    }
}
