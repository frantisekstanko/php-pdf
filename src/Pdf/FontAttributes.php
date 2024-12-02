<?php

declare(strict_types=1);

namespace Stanko\Pdf;

final readonly class FontAttributes
{
    /**
     * @param array{
     *   0: float,
     *   1: float,
     *   2: float,
     *   3: float
     * } $boundingBox
     */
    public function __construct(
        private float $ascent,
        private float $descent,
        private float $capHeight,
        private int $flags,
        private array $boundingBox,
        private int $italicAngle,
        private float $stemV,
        private float $missingWidth,
    ) {
    }

    /**
     * @return array{
     *  Ascent: int,
     *  Descent: int,
     *  CapHeight: int,
     *  Flags: int,
     *  FontBBox: string,
     *  ItalicAngle: int,
     *  StemV: int,
     *  MissingWidth: int,
     * }
     */
    public function toArray(): array
    {
        return [
            'Ascent' => (int) round($this->ascent),
            'Descent' => (int) round($this->descent),
            'CapHeight' => (int) round($this->capHeight),
            'Flags' => $this->flags,
            'FontBBox' => '[' .
                round($this->boundingBox[0]) .
                ' ' .
                round($this->boundingBox[1]) .
                ' ' .
                round($this->boundingBox[2]) .
                ' ' .
                round($this->boundingBox[3]) .
                ']',
            'ItalicAngle' => $this->italicAngle,
            'StemV' => (int) round($this->stemV),
            'MissingWidth' => (int) round($this->missingWidth),
        ];
    }

    public function getMissingWidth(): int
    {
        return (int) round($this->missingWidth);
    }
}
