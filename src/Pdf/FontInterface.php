<?php

declare(strict_types=1);

namespace Stanko\Pdf;

interface FontInterface
{
    /**
     * @return string absolute path to font file (TTF)
     */
    public function getFontFilePath(): string;

    /**
     * @return float font size in points
     */
    public function getFontSize(): float;
}
