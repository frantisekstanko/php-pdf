<?php

declare(strict_types=1);

namespace Stanko\Pdf\Exception;

use RuntimeException;

final class FailedToDrawCellException extends RuntimeException implements ExceptionInterface
{
    public static function becauseNoFontHasBeenSelected(): self
    {
        return new self('You must call ->withFont() before calling ->drawCell()');
    }
}
