<?php

declare(strict_types=1);

namespace Stanko\Pdf;

interface FontInterface
{
    public function getTtfFilePath(): string;

    public function getSizeInPoints(): float;
}
