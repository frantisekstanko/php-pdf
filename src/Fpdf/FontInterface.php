<?php

declare(strict_types=1);

namespace Stanko\Fpdf;

interface FontInterface
{
    public function getTtfFilePath(): string;

    public function getSizeInPoints(): float;
}
