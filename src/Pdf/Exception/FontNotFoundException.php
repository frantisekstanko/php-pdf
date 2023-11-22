<?php

declare(strict_types=1);

namespace Stanko\Pdf\Exception;

use RuntimeException;

final class FontNotFoundException extends RuntimeException implements ExceptionInterface
{
}
