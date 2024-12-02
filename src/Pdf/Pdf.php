<?php

namespace Stanko\Pdf;

use DateTimeImmutable;
use Stanko\Pdf\Exception\AliasMustBeSetBeforeLoadingFontsException;
use Stanko\Pdf\Exception\CannotAddPageToClosedDocumentException;
use Stanko\Pdf\Exception\CannotOpenImageFileException;
use Stanko\Pdf\Exception\CompressionException;
use Stanko\Pdf\Exception\ContentBufferException;
use Stanko\Pdf\Exception\FailedToDrawCellException;
use Stanko\Pdf\Exception\FailedToWriteStringException;
use Stanko\Pdf\Exception\FailedToWriteTextException;
use Stanko\Pdf\Exception\FontNotFoundException;
use Stanko\Pdf\Exception\HeadersAlreadySentException;
use Stanko\Pdf\Exception\IncorrectFontDefinitionException;
use Stanko\Pdf\Exception\IncorrectPageLinksException;
use Stanko\Pdf\Exception\InvalidHeightException;
use Stanko\Pdf\Exception\InvalidLayoutModeException;
use Stanko\Pdf\Exception\InvalidWidthException;
use Stanko\Pdf\Exception\NoFontHasBeenSetException;
use Stanko\Pdf\Exception\NoPageHasBeenAddedException;
use Stanko\Pdf\Exception\TheDocumentIsClosedException;
use Stanko\Pdf\Exception\UndefinedFontException;
use Stanko\Pdf\Exception\UnsupportedImageTypeException;

final class Pdf
{
    private int $currentPageNumber = 0;
    private int $currentObjectNumber = 2;

    /** @var array<int, int> */
    private array $objectOffsets;
    private string $pdfFileBuffer = '';

    /** @var array<int, string> */
    private array $rawPageData = [];
    private DocumentState $currentDocumentState;
    private bool $compressionEnabled;
    private float $scaleFactor;
    private PageOrientation $currentOrientation;

    private PageSize $currentPageSize;

    private PageRotation $currentPageRotation;

    /** @var array<int, array{
     *   size: array<float>,
     *   rotation: PageRotation,
     *   n: int,
     * }>
     */
    private array $pageInfo = [];
    private float $pageWidth;
    private float $pageHeight;
    private float $leftMargin;
    private float $topMargin;
    private float $rightMargin;
    private float $pageBreakMargin;
    private float $interiorCellMargin;
    private float $currentXPosition;
    private float $currentYPosition;
    private ?float $lastPrintedCellHeight;
    private float $lineWidth;
    private int $withDpi = 96;

    /** @var array<string, array{
     * i: int,
     * type: string,
     * name: string,
     * attributes: FontAttributes,
     * up: float,
     * ut: float,
     * cw: string,
     * ttffile: string,
     * subset: array<int, int>,
     * n: int,
     * }> */
    private array $usedFonts = [];

    private ?FontInterface $currentFont = null;
    private bool $isUnderline = false;

    private float $currentFontSizeInPoints = 12;
    private float $currentFontSize;
    private string $drawColor = '0 G';
    private string $fillColor = '0 g';
    private string $textColor = '0 g';
    private bool $fillAndTextColorDiffer = false;
    private float $wordSpacing = 0;

    /** @var array<string, array<mixed>> */
    private array $usedImages = [];

    /** @var array<int, array<int, array{
     *  0: float,
     *  1: float,
     *  2: float,
     *  3: float,
     *  4: mixed,
     *  5?: int,
     * }>> */
    private array $pageLinks;

    /** @var array<int, array{0: int, 1: float}> */
    private array $internalLinks = [];
    private bool $automaticPageBreaking;
    private float $pageBreakThreshold;
    private ?string $aliasForTotalNumberOfPages = null;
    private string $layoutMode = 'default';

    private Metadata $metadata;
    private string $pdfVersion = '1.3';

    private ?float $withWidth;
    private ?float $withHeight;

    private Units $units;

    public function __construct()
    {
        $this->currentDocumentState = DocumentState::NOT_INITIALIZED;

        $this->metadata = Metadata::empty();

        $this->units = Units::MILLIMETERS;
        $this->scaleFactor = $this->units->getScaleFactor();

        $this->currentOrientation = PageOrientation::PORTRAIT;

        $this->setPageSize(PageSize::a4());

        $this->currentPageRotation = PageRotation::NONE;

        $margin = 28.35 / $this->scaleFactor;
        $this->leftMargin = $margin;
        $this->topMargin = $margin;
        $this->rightMargin = $margin;
        $this->interiorCellMargin = $margin / 10;
        $this->lineWidth = .567 / $this->scaleFactor;

        $this->currentXPosition = $this->leftMargin;
        $this->currentYPosition = $this->topMargin;

        $this->withWidth = null;
        $this->withHeight = null;

        $this->automaticPageBreaking = true;
        $this->pageBreakMargin = 2 * $margin;
        $this->recalculatePageBreakThreshold();

        $this->enableCompressionIfAvailable();
    }

    public function withDpi(int $dpi): self
    {
        $pdf = clone $this;

        $pdf->withDpi = $dpi;

        return $pdf;
    }

    public function inUnits(Units $units): self
    {
        $pdf = clone $this;

        $pdf->units = $units;
        $pdf->scaleFactor = $units->getScaleFactor();

        $pdf->recalculatePageDimensions();

        $margin = 28.35 / $pdf->scaleFactor;
        $pdf = $pdf->withLeftMargin($margin);
        $pdf = $pdf->withTopMargin($margin);
        $pdf->interiorCellMargin = $margin / 10;
        $pdf->lineWidth = .567 / $pdf->scaleFactor;

        return $pdf;
    }

    public function withAutomaticWidth(): self
    {
        $pdf = clone $this;

        $pdf->withWidth = null;

        return $pdf;
    }

    public function withAutomaticHeight(): self
    {
        $pdf = clone $this;

        $pdf->withHeight = null;

        return $pdf;
    }

    public function withWidth(float $width): self
    {
        if ($width <= 0) {
            throw new InvalidWidthException();
        }

        $pdf = clone $this;

        $pdf->withWidth = $width;

        return $pdf;
    }

    public function withHeight(float $height): self
    {
        if ($height <= 0) {
            throw new InvalidHeightException();
        }

        $pdf = clone $this;

        $pdf->withHeight = $height;

        return $pdf;
    }

    public function withLeftMargin(float $margin): self
    {
        $pdf = clone $this;

        $pdf->leftMargin = $margin;

        if ($pdf->currentPageNumber > 0 && $pdf->currentXPosition < $margin) {
            $pdf->currentXPosition = $margin;
        }

        return $pdf;
    }

    public function withTopMargin(float $margin): self
    {
        $pdf = clone $this;

        $pdf->topMargin = $margin;

        return $pdf;
    }

    public function withRightMargin(float $margin): self
    {
        $pdf = clone $this;

        $pdf->rightMargin = $margin;

        return $pdf;
    }

    public function withAutomaticPageBreaking(float $threshold = 0): self
    {
        $pdf = clone $this;

        $pdf->automaticPageBreaking = true;
        $pdf->pageBreakMargin = $threshold;
        $pdf->recalculatePageBreakThreshold();

        return $pdf;
    }

    public function withoutAutomaticPageBreaking(): self
    {
        $pdf = clone $this;

        $pdf->automaticPageBreaking = false;

        return $pdf;
    }

    public function withLayout(string $layout = 'default'): self
    {
        if (
            $layout == 'single'
            || $layout == 'continuous'
            || $layout == 'two'
            || $layout == 'default'
        ) {
            $pdf = clone $this;

            $pdf->layoutMode = $layout;

            return $pdf;
        }

        throw new InvalidLayoutModeException();
    }

    public function withCompression(): self
    {
        $pdf = clone $this;

        $pdf->enableCompressionIfAvailable();

        if ($pdf->compressionEnabled === false) {
            throw new CompressionException('gzcompress() is not available');
        }

        return $pdf;
    }

    public function withoutCompression(): self
    {
        $pdf = clone $this;

        $pdf->compressionEnabled = false;

        return $pdf;
    }

    public function withTitle(string $title): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->withTitle($title);

        return $pdf;
    }

    public function byAuthor(string $author): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->withAuthor($author);

        return $pdf;
    }

    public function withSubject(string $subject): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->withSubject($subject);

        return $pdf;
    }

    public function withKeywords(string $keywords): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->withKeywords($keywords);

        return $pdf;
    }

    public function createdBy(string $creator): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->createdBy($creator);

        return $pdf;
    }

    public function withAliasForTotalNumberOfPages(
        string $alias = '{totalPages}',
    ): self {
        if ($this->usedFonts !== []) {
            throw new AliasMustBeSetBeforeLoadingFontsException();
        }

        $pdf = clone $this;

        $pdf->aliasForTotalNumberOfPages = $alias;

        return $pdf;
    }

    public function addPage(): self
    {
        if ($this->currentDocumentState === DocumentState::CLOSED) {
            throw new CannotAddPageToClosedDocumentException();
        }

        $pdf = clone $this;

        if ($pdf->currentPageNumber > 0) {
            $pdf->endPage();
        }
        $pdf->startNewPage();
        $pdf->setLineCapStyleToSquare();
        $pdf->appendLineWidthToPdfBuffer();
        if ($pdf->currentFont) {
            $pdf->writeFontInformationToDocument($pdf->currentFont);
        }
        if ($pdf->drawColor != '0 G') {
            $pdf->out($pdf->drawColor);
        }
        if ($pdf->fillColor != '0 g') {
            $pdf->out($this->fillColor);
        }

        return $pdf;
    }

    public function getCurrentPageNumber(): int
    {
        return $this->currentPageNumber;
    }

    public function withDrawColor(Color $color): self
    {
        $pdf = clone $this;

        if ($color->isBlack()) {
            $pdf->drawColor = sprintf('%.3F G', 0);

            return $pdf;
        }

        $pdf->drawColor = sprintf(
            '%.3F %.3F %.3F RG',
            $color->getRed() / 255,
            $color->getGreen() / 255,
            $color->getBlue() / 255,
        );

        if ($pdf->currentPageNumber > 0) {
            $pdf->out($pdf->drawColor);
        }

        return $pdf;
    }

    public function withFillColor(Color $color): self
    {
        $pdf = clone $this;

        if ($color->isBlack()) {
            $pdf->fillColor = sprintf('%.3F g', 0);

            return $pdf;
        }

        $pdf->fillColor = sprintf(
            '%.3F %.3F %.3F rg',
            $color->getRed() / 255,
            $color->getGreen() / 255,
            $color->getBlue() / 255,
        );

        $pdf->fillAndTextColorDiffer = ($pdf->fillColor !== $pdf->textColor);
        if ($pdf->currentPageNumber > 0) {
            $pdf->out($pdf->fillColor);
        }

        return $pdf;
    }

    public function withTextColor(Color $color): self
    {
        $pdf = clone $this;

        if ($color->isBlack()) {
            $pdf->textColor = sprintf('%.3F g', 0);

            return $pdf;
        }

        $pdf->textColor = sprintf(
            '%.3F %.3F %.3F rg',
            $color->getRed() / 255,
            $color->getGreen() / 255,
            $color->getBlue() / 255,
        );
        $pdf->fillAndTextColorDiffer = ($pdf->fillColor != $pdf->textColor);

        return $pdf;
    }

    public function withLineWidth(float $width): self
    {
        $pdf = clone $this;

        $pdf->lineWidth = $width;

        if ($pdf->currentPageNumber > 0) {
            $pdf->appendLineWidthToPdfBuffer();
        }

        return $pdf;
    }

    public function drawLine(
        float $fromX,
        float $fromY,
        float $toX,
        float $toY,
    ): self {
        $pdf = clone $this;

        $pdf->out(
            sprintf(
                '%.2F %.2F m %.2F %.2F l S',
                $fromX * $pdf->scaleFactor,
                ($pdf->pageHeight - $fromY) * $pdf->scaleFactor,
                $toX * $pdf->scaleFactor,
                ($pdf->pageHeight - $toY) * $pdf->scaleFactor
            )
        );

        return $pdf;
    }

    public function drawRectangle(
        float $xPosition,
        float $yPosition,
        float $width,
        float $height,
        RectangleStyle $style,
    ): self {
        $pdf = clone $this;

        $pdf->out(
            sprintf(
                '%.2F %.2F %.2F %.2F re %s',
                $xPosition * $pdf->scaleFactor,
                ($pdf->pageHeight - $yPosition) * $pdf->scaleFactor,
                $width * $pdf->scaleFactor,
                -$height * $pdf->scaleFactor,
                $style->toPdfOperation(),
            )
        );

        return $pdf;
    }

    public function loadFont(
        FontInterface $font,
    ): self {
        if (isset($this->usedFonts[$font::class])) {
            return $this;
        }

        $ttfstat = stat($font->getFontFilePath());

        if ($ttfstat === false) {
            throw new FontNotFoundException($font->getFontFilePath());
        }

        $ttfParser = new TtfParser();
        $ttfParser->getMetrics($font->getFontFilePath());
        $charWidths = $ttfParser->charWidths;
        $name = (string) preg_replace('/[ ()]/', '', $ttfParser->fullName);

        $attributes = new FontAttributes(
            ascent: $ttfParser->ascent,
            descent: $ttfParser->descent,
            capHeight: $ttfParser->capHeight,
            flags: $ttfParser->flags,
            boundingBox: $ttfParser->bbox,
            italicAngle: $ttfParser->italicAngle,
            stemV: $ttfParser->stemV,
            missingWidth: $ttfParser->defaultWidth,
        );

        $sbarr = range(0, 32);

        if ($this->aliasForTotalNumberOfPages !== null) {
            $sbarr = range(0, 57);
        }

        $fontType = 'TTF';

        $pdf = clone $this;

        $pdf->usedFonts[$font::class] = [
            'i' => count($this->usedFonts) + 1,
            'type' => $fontType,
            'name' => $name,
            'attributes' => $attributes,
            'up' => round($ttfParser->underlinePosition),
            'ut' => round($ttfParser->underlineThickness),
            'cw' => $charWidths,
            'ttffile' => $font->getFontFilePath(),
            'subset' => $sbarr,
            'n' => 0,
        ];

        return $pdf;
    }

    public function withUnderline(): self
    {
        $pdf = clone $this;

        $pdf->isUnderline = true;

        return $pdf;
    }

    public function withoutUnderline(): self
    {
        $pdf = clone $this;

        $pdf->isUnderline = false;

        return $pdf;
    }

    public function withFont(
        FontInterface $font,
    ): self {
        if (!isset($this->usedFonts[$font::class])) {
            throw new UndefinedFontException();
        }

        $pdf = clone $this;

        $pdf->setFont($font);

        return $pdf;
    }

    public function createLink(): int
    {
        $newId = count($this->internalLinks) + 1;

        $this->internalLinks[$newId] = [0, 0];

        return $newId;
    }

    public function SetLink(int $link, float $y = 0, int $page = -1): void
    {
        // Set destination of internal link
        if ($y == -1) {
            $y = $this->currentYPosition;
        }
        if ($page == -1) {
            $page = $this->currentPageNumber;
        }
        $this->internalLinks[$link] = [$page, $y];
    }

    public function addLink(
        float $x,
        float $y,
        float $w,
        float $h,
        mixed $link,
    ): self {
        $pdf = clone $this;

        $pdf->pageLinks[$pdf->currentPageNumber][] = [
            $x * $pdf->scaleFactor,
            $pdf->pageHeightInPoints() - $y * $pdf->scaleFactor,
            $w * $pdf->scaleFactor,
            $h * $pdf->scaleFactor, $link,
        ];

        return $pdf;
    }

    /**
     * Writes a string at a specific position, without any line-breaking.
     *
     * If you want to write a paragraph, it's better to use $pdf->writeText().
     */
    public function writeString(float $x, float $y, string $string): self
    {
        $pdf = clone $this;

        if ($pdf->currentFont === null) {
            throw FailedToWriteStringException::becauseNoFontHasBeenSelected();
        }

        if ($string === '') {
            throw FailedToWriteStringException::becauseStringToWriteIsEmpty();
        }

        $pdfString = '(' . $this->escapeSpecialCharacters(
            $this->utf8ToUtf16Be($string)
        ) . ')';

        foreach ($pdf->utf8StringToUnicodeArray($string) as $uni) {
            $pdf->usedFonts[$pdf->currentFont::class]['subset'][$uni] = $uni;
        }
        $s = sprintf(
            'BT %.2F %.2F Td %s Tj ET',
            $x * $pdf->scaleFactor,
            ($pdf->pageHeight - $y) * $pdf->scaleFactor,
            $pdfString,
        );
        if ($pdf->isUnderline) {
            $s .= ' ' . $pdf->_dounderline($x, $y, $string);
        }
        if ($pdf->fillAndTextColorDiffer) {
            $s = 'q ' . $pdf->textColor . ' ' . $s . ' Q';
        }
        $pdf->out($s);

        return $pdf;
    }

    public function drawCell(
        string $txt = '',
        mixed $border = 0,
        int $ln = 0,
        string $align = '',
        bool $fill = false,
        mixed $link = '',
    ): self {
        $pdf = clone $this;

        $pdf = $pdf->automaticPageBreak();
        $cellWidth = $pdf->withWidth;
        if ($pdf->withWidth === null) {
            $cellWidth = $pdf->pageWidth - $pdf->rightMargin - $pdf->currentXPosition;
        }
        $appendToPdfBuffer = '';
        if ($fill || $border === 1) {
            $appendToPdfBuffer = sprintf(
                '%.2F %.2F %.2F %.2F re %s ',
                $pdf->currentXPosition * $pdf->scaleFactor,
                ($pdf->pageHeight - $pdf->currentYPosition) * $pdf->scaleFactor,
                $cellWidth * $pdf->scaleFactor,
                -$pdf->withHeight * $pdf->scaleFactor,
                $pdf->getRectangleAttribute($fill, $border),
            );
        }
        if (is_string($border)) {
            $x = $pdf->currentXPosition;
            $y = $pdf->currentYPosition;
            if (strpos($border, 'L') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $pdf->scaleFactor,
                    ($pdf->pageHeight - $y) * $pdf->scaleFactor,
                    $x * $pdf->scaleFactor,
                    ($pdf->pageHeight - ($y + $pdf->withHeight)) * $pdf->scaleFactor
                );
            }
            if (strpos($border, 'T') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $pdf->scaleFactor,
                    ($pdf->pageHeight - $y) * $pdf->scaleFactor,
                    ($x + $cellWidth) * $pdf->scaleFactor,
                    ($pdf->pageHeight - $y) * $pdf->scaleFactor
                );
            }
            if (strpos($border, 'R') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    ($x + $cellWidth) * $pdf->scaleFactor,
                    ($pdf->pageHeight - $y) * $pdf->scaleFactor,
                    ($x + $cellWidth) * $pdf->scaleFactor,
                    ($pdf->pageHeight - ($y + $pdf->withHeight)) * $pdf->scaleFactor
                );
            }
            if (strpos($border, 'B') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $pdf->scaleFactor,
                    ($pdf->pageHeight - ($y + $pdf->withHeight)) * $pdf->scaleFactor,
                    ($x + $cellWidth) * $pdf->scaleFactor,
                    ($pdf->pageHeight - ($y + $pdf->withHeight)) * $pdf->scaleFactor
                );
            }
        }
        if ($txt !== '') {
            if ($pdf->currentFont === null) {
                throw FailedToDrawCellException::becauseNoFontHasBeenSelected();
            }
            if ($align == 'R') {
                $dx = $cellWidth - $pdf->interiorCellMargin - $pdf->getStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($cellWidth - $pdf->getStringWidth($txt)) / 2;
            } else {
                $dx = $pdf->interiorCellMargin;
            }
            if ($pdf->fillAndTextColorDiffer) {
                $appendToPdfBuffer .= 'q ' . $pdf->textColor . ' ';
            }
            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($pdf->wordSpacing) {
                foreach ($pdf->utf8StringToUnicodeArray($txt) as $uni) {
                    $pdf->usedFonts[$pdf->currentFont::class]['subset'][$uni] = $uni;
                }
                $space = $pdf->escapeSpecialCharacters($pdf->utf8ToUtf16Be(' '));
                $appendToPdfBuffer .= sprintf(
                    'BT 0 Tw %.2F %.2F Td [',
                    ($pdf->currentXPosition + $dx) * $pdf->scaleFactor,
                    ($pdf->pageHeight - ($pdf->currentYPosition + .5 * $pdf->withHeight + .3 * $pdf->currentFontSize)) * $pdf->scaleFactor
                );
                $t = explode(' ', $txt);
                $numt = count($t);
                for ($i = 0; $i < $numt; ++$i) {
                    $tx = $t[$i];
                    $tx = '(' . $pdf->escapeSpecialCharacters($pdf->utf8ToUtf16Be($txt)) . ')';
                    $appendToPdfBuffer .= sprintf('%s ', $tx);
                    if (($i + 1) < $numt) {
                        $adj = - ($pdf->wordSpacing * $pdf->scaleFactor) * 1000 / $pdf->currentFontSizeInPoints;
                        $appendToPdfBuffer .= sprintf('%d(%s) ', $adj, $space);
                    }
                }
                $appendToPdfBuffer .= '] TJ';
                $appendToPdfBuffer .= ' ET';
            } else {
                $txt2 = '(' . $pdf->escapeSpecialCharacters($pdf->utf8ToUtf16Be($txt)) . ')';
                foreach ($pdf->utf8StringToUnicodeArray($txt) as $uni) {
                    $pdf->usedFonts[$pdf->currentFont::class]['subset'][$uni] = $uni;
                }
                $appendToPdfBuffer .= sprintf(
                    'BT %.2F %.2F Td %s Tj ET',
                    ($pdf->currentXPosition + $dx) * $pdf->scaleFactor,
                    ($pdf->pageHeight - ($pdf->currentYPosition + .5 * $pdf->withHeight + .3 * $pdf->currentFontSize)) * $pdf->scaleFactor,
                    $txt2
                );
            }
            if ($pdf->isUnderline) {
                $appendToPdfBuffer .= ' ' . $pdf->_dounderline(
                    $pdf->currentXPosition + $dx,
                    $pdf->currentYPosition + .5 * $pdf->withHeight + .3 * $pdf->currentFontSize,
                    $txt,
                );
            }
            if ($pdf->fillAndTextColorDiffer) {
                $appendToPdfBuffer .= ' Q';
            }
            if ($link) {
                $pdf = $pdf->addLink(
                    $pdf->currentXPosition + $dx,
                    $pdf->currentYPosition + .5 * $pdf->withHeight - .5 * $pdf->currentFontSize,
                    $pdf->getStringWidth($txt),
                    $pdf->currentFontSize,
                    $link,
                );
            }
        }
        if ($appendToPdfBuffer) {
            $pdf->out($appendToPdfBuffer);
        }
        $pdf->lastPrintedCellHeight = $pdf->withHeight;
        if ($ln > 0) {
            // Go to next line
            $pdf->currentYPosition += $pdf->withHeight;
            if ($ln == 1) {
                $pdf->currentXPosition = $pdf->leftMargin;
            }
        } else {
            $pdf->currentXPosition += $cellWidth;
        }

        return $pdf;
    }

    public function drawMultiCell(
        float $h,
        string $txt,
        ?CellBorder $cellBorder = null,
        string $align = 'J',
        bool $fill = false,
    ): self {
        if ($this->currentFont === null) {
            throw new NoFontHasBeenSetException();
        }

        $pdf = clone $this;

        if ($cellBorder === null) {
            $cellBorder = CellBorder::none();
        }

        $cellWidth = $pdf->withWidth;
        if ($cellWidth == 0) {
            $cellWidth = $pdf->pageWidth - $pdf->rightMargin - $pdf->currentXPosition;
        }
        $maximumWidth = ($cellWidth - 2 * $pdf->interiorCellMargin);
        $string = str_replace("\r", '', $txt);
        $nb = mb_strlen($string, 'utf-8');
        $border1 = 0;
        $border2 = '';
        if ($cellBorder->hasAnySide()) {
            if ($cellBorder->hasAllSides()) {
                $border1 = 'LRT';
                $border2 = 'LR';
            } else {
                $border2 = '';
                if ($cellBorder->hasLeft()) {
                    $border2 .= 'L';
                }
                if ($cellBorder->hasRight()) {
                    $border2 .= 'R';
                }
                $border1 = ($cellBorder->hasTop()) ? $border2 . 'T' : $border2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $stringWidth = 0;
        $ns = 0;
        $currentLine = 1;
        $ls = 0;
        while ($i < $nb) {
            // Get next character
            $c = mb_substr($string, $i, 1, 'UTF-8');
            if ($c === "\n") {
                // Explicit line break
                if ($pdf->wordSpacing > 0) {
                    $pdf->wordSpacing = 0;
                    $pdf->out('0 Tw');
                }
                $pdf->withWidth = $cellWidth;
                $pdf->withHeight = $h;

                $pdf = $pdf->drawCell(
                    txt: mb_substr($string, $j, $i - $j, 'UTF-8'),
                    border: $border1,
                    ln: 2,
                    align: $align,
                    fill: $fill,
                );
                ++$i;
                $sep = -1;
                $j = $i;
                $stringWidth = 0;
                $ns = 0;
                ++$currentLine;
                if ($cellBorder->hasAnySide() && $currentLine === 2) {
                    $border1 = $border2;
                }

                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $stringWidth;
                ++$ns;
            }

            $stringWidth += $pdf->getStringWidth($c);

            if ($stringWidth > $maximumWidth) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        ++$i;
                    }
                    if ($pdf->wordSpacing > 0) {
                        $pdf->wordSpacing = 0;
                        $pdf->out('0 Tw');
                    }
                    $pdf->withWidth = $cellWidth;
                    $pdf->withHeight = $h;
                    $pdf = $pdf->drawCell(mb_substr($string, $j, $i - $j, 'UTF-8'), $border1, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $pdf->wordSpacing = ($ns > 1) ? ($maximumWidth - $ls) / ($ns - 1) : 0;
                        $pdf->out(sprintf('%.3F Tw', $pdf->wordSpacing * $pdf->scaleFactor));
                    }
                    $pdf->withWidth = $cellWidth;
                    $pdf->withHeight = $h;

                    $pdf = $pdf->drawCell(mb_substr($string, $j, $sep - $j, 'UTF-8'), $border1, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $stringWidth = 0;
                $ns = 0;
                ++$currentLine;
                if ($cellBorder->hasAnySide() && $currentLine === 2) {
                    $border1 = $border2;
                }
            } else {
                ++$i;
            }
        }
        // Last chunk
        if ($pdf->wordSpacing > 0) {
            $pdf->wordSpacing = 0;
            $pdf->out('0 Tw');
        }
        if ($cellBorder->hasBottom()) {
            $border1 .= 'B';
        }
        $pdf->withWidth = $cellWidth;
        $pdf->withHeight = $h;
        $pdf = $pdf->drawCell(mb_substr($string, $j, $i - $j, 'UTF-8'), $border1, 2, $align, $fill);
        $pdf->currentXPosition = $pdf->leftMargin;

        return $pdf;
    }

    /**
     * Use this method to write multi-line text.
     *
     * Line-breaks are inserted automatically when the end of the page
     * is reached. "\n" is also respected to force a line-break.
     */
    public function writeText(float $h, string $text, string $link = ''): self
    {
        if ($this->currentFont === null) {
            throw FailedToWriteTextException::becauseNoFontHasBeenSelected();
        }

        if ($text === '') {
            throw FailedToWriteTextException::becauseStringToWriteIsEmpty();
        }

        $pdf = clone $this;

        $remainingWidth = (
            ($pdf->pageWidth - $pdf->rightMargin - $pdf->currentXPosition)
            - 2 * $pdf->interiorCellMargin
        );
        $cleanText = str_replace("\r", '', (string) $text);
        $cleanTextLength = mb_strlen($cleanText, 'UTF-8');
        if ($cleanTextLength === 1 && $cleanText === ' ') {
            $pdf->currentXPosition += $pdf->getStringWidth($cleanText);

            return $pdf;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $stringWidth = 0;
        $nl = 1;
        while ($i < $cleanTextLength) {
            // Get next character
            $c = mb_substr($cleanText, $i, 1, 'UTF-8');
            if ($c == "\n") {
                // Explicit line break
                $pdf->withWidth = $remainingWidth;
                $pdf->withHeight = $h;
                $pdf = $pdf->drawCell(mb_substr($cleanText, $j, $i - $j, 'UTF-8'), 0, 2, '', false, $link);
                ++$i;
                $sep = -1;
                $j = $i;
                $stringWidth = 0;
                if ($nl == 1) {
                    $pdf->currentXPosition = $pdf->leftMargin;
                    $remainingWidth = $pdf->pageWidth - $pdf->rightMargin - $pdf->currentXPosition;
                    $remainingWidth = ($remainingWidth - 2 * $pdf->interiorCellMargin);
                }
                ++$nl;

                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }

            $stringWidth += $pdf->getStringWidth($c);

            if ($stringWidth > $remainingWidth) {
                // Automatic line break
                if ($sep == -1) {
                    if ($pdf->currentXPosition > $pdf->leftMargin) {
                        // Move to next line
                        $pdf->currentXPosition = $pdf->leftMargin;
                        $pdf->currentYPosition += $h;
                        $remainingWidth = $pdf->pageWidth - $pdf->rightMargin - $pdf->currentXPosition;
                        $remainingWidth = ($remainingWidth - 2 * $pdf->interiorCellMargin);
                        ++$i;
                        ++$nl;

                        continue;
                    }
                    if ($i == $j) {
                        ++$i;
                    }
                    $pdf->withWidth = $remainingWidth;
                    $pdf->withHeight = $h;
                    $pdf = $pdf->drawCell(mb_substr($cleanText, $j, $i - $j, 'UTF-8'), 0, 2, '', false, $link);
                } else {
                    $pdf = $pdf->drawCell(mb_substr($cleanText, $j, $sep - $j, 'UTF-8'), 0, 2, '', false, $link);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $stringWidth = 0;
                if ($nl == 1) {
                    $pdf->currentXPosition = $pdf->leftMargin;
                    $remainingWidth = $pdf->pageWidth - $pdf->rightMargin - $pdf->currentXPosition;
                    $remainingWidth = ($remainingWidth - 2 * $pdf->interiorCellMargin);
                }
                ++$nl;
            } else {
                ++$i;
            }
        }
        // Last chunk
        if ($i != $j) {
            $pdf->withWidth = $stringWidth;
            $pdf->withHeight = $h;

            $pdf = $pdf->drawCell(mb_substr($cleanText, $j, $i - $j, 'UTF-8'), 0, 0, '', false, $link);
        }

        return $pdf;
    }

    public function onNextRow(): self
    {
        $pdf = clone $this;

        $pdf->currentXPosition = $pdf->leftMargin;
        $pdf->currentYPosition += $pdf->lastPrintedCellHeight ?? 0;

        return $pdf;
    }

    public function insertImage(
        string $file,
        ?float $xPosition = null,
        ?float $yPosition = null,
        float $imageWidth = 0,
        float $imageHeight = 0,
        string $fileType = '',
        string $link = '',
    ): self {
        if ($file === '') {
            throw new CannotOpenImageFileException($file);
        }

        $pdf = clone $this;

        if (!isset($pdf->usedImages[$file])) {
            if ($fileType == '') {
                $pos = strrpos($file, '.');
                if (!$pos) {
                    throw new UnsupportedImageTypeException(
                        'Image file has no extension and no type was specified: ' . $file
                    );
                }
                $fileType = substr($file, $pos + 1);
            }
            $fileType = strtolower($fileType);

            $info = (new ImageParser())->parseImage($file, $fileType);

            $info['i'] = count($pdf->usedImages) + 1;
            $pdf->usedImages[$file] = $info;
        } else {
            $info = $pdf->usedImages[$file];
        }

        // Automatic width and height calculation if needed
        if ($imageWidth == 0 && $imageHeight == 0) {
            // Put image at dpi
            $imageWidth = - $this->withDpi;
            $imageHeight = - $this->withDpi;
        }
        if ($imageWidth < 0) {
            $imageWidth = -$info['w'] * 72 / $imageWidth / $pdf->scaleFactor;
        }
        if ($imageHeight < 0) {
            $imageHeight = -$info['h'] * 72 / $imageHeight / $pdf->scaleFactor;
        }
        if ($imageWidth == 0) {
            $imageWidth = $imageHeight * $info['w'] / $info['h'];
        }
        if ($imageHeight == 0) {
            $imageHeight = $imageWidth * $info['h'] / $info['w'];
        }

        // Flowing mode
        if ($yPosition === null) {
            if (
                $pdf->currentYPosition + $imageHeight > $pdf->pageBreakThreshold
                && $pdf->automaticPageBreaking
                && $pdf->currentYPosition !== $pdf->topMargin
            ) {
                // Automatic page break
                $x2 = $pdf->currentXPosition;
                $pdf = $pdf->addPage();
                $pdf->currentXPosition = $x2;
            }
            $yPosition = $pdf->currentYPosition;
            $pdf->currentYPosition += $imageHeight;
        }

        if ($xPosition === null) {
            $xPosition = $pdf->currentXPosition;
        }
        $pdf->out(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $imageWidth * $pdf->scaleFactor,
            $imageHeight * $pdf->scaleFactor,
            $xPosition * $pdf->scaleFactor,
            ($pdf->pageHeight - ($yPosition + $imageHeight)) * $pdf->scaleFactor,
            $info['i'],
        ));
        if ($link) {
            $pdf = $pdf->addLink($xPosition, $yPosition, $imageWidth, $imageHeight, $link);
        }

        return $pdf;
    }

    public function GetPageWidth(): float
    {
        return $this->pageWidth;
    }

    public function GetPageHeight(): float
    {
        return $this->pageHeight;
    }

    public function getX(): float
    {
        return $this->currentXPosition;
    }

    public function atX(float $x): self
    {
        $pdf = clone $this;

        if ($x >= 0) {
            $pdf->currentXPosition = $x;

            return $pdf;
        }

        $pdf->currentXPosition = $pdf->pageWidth + $x;

        return $pdf;
    }

    public function getY(): float
    {
        return $this->currentYPosition;
    }

    public function atY(float $y): self
    {
        $pdf = clone $this;

        if ($y >= 0) {
            $pdf->currentYPosition = $y;

            return $pdf;
        }

        $pdf->currentYPosition = $this->pageHeight + $y;

        return $pdf;
    }

    public function lowerBy(float $y): self
    {
        $pdf = clone $this;

        $pdf->currentYPosition += $y;

        return $pdf;
    }

    public function rightwardBy(float $x): self
    {
        $pdf = clone $this;

        $pdf->currentXPosition += $x;

        return $pdf;
    }

    public function downloadFile(string $fileName): void
    {
        $pdf = $this->closeDocument();
        $pdf->checkOutput();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; ' . $pdf->_httpencode('filename', $fileName));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdf->pdfFileBuffer;
    }

    public function saveAsFile(string $fileName): void
    {
        $pdf = $this->closeDocument();
        file_put_contents($fileName, $pdf->pdfFileBuffer);
    }

    public function toString(): string
    {
        $pdf = $this->closeDocument();

        return $pdf->pdfFileBuffer;
    }

    public function toStandardOutput(string $fileName): void
    {
        $pdf = $this->closeDocument();
        $pdf->checkOutput();
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; ' . $pdf->_httpencode('filename', $fileName));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        echo $pdf->pdfFileBuffer;
    }

    public function createdAt(DateTimeImmutable $createdAt): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->createdAt($createdAt);

        return $pdf;
    }

    public function withPageOrientation(PageOrientation $pageOrientation): self
    {
        $pdf = clone $this;

        $pdf->currentOrientation = $pageOrientation;

        return $pdf;
    }

    public function withPageRotation(PageRotation $pageRotation): self
    {
        $pdf = clone $this;

        $pdf->currentPageRotation = $pageRotation;

        return $pdf;
    }

    public function withPageSize(PageSize $pageSize): self
    {
        $pdf = clone $this;

        $pdf->currentPageSize = $pageSize;

        return $pdf;
    }

    private function appendLineWidthToPdfBuffer(): void
    {
        $this->out(sprintf('%.2F w', $this->lineWidth * $this->scaleFactor));
    }

    private function setLineCapStyleToSquare(): void
    {
        $this->out('2 J');
    }

    private function setFont(
        FontInterface $font,
    ): void {
        if ($this->currentFont === $font) {
            return;
        }

        $this->currentFont = $font;
        $this->currentFontSizeInPoints = $font->getFontSize();
        $this->currentFontSize = $font->getFontSize() / $this->scaleFactor;

        if ($this->currentPageNumber > 0) {
            $this->writeFontInformationToDocument($font);
        }
    }

    private function writeFontInformationToDocument(
        FontInterface $font,
    ): void {
        $this->out(
            sprintf(
                'BT /F%d %.2F Tf ET',
                $this->usedFonts[$font::class]['i'],
                $this->currentFontSizeInPoints,
            )
        );
    }

    private function setPageSize(PageSize $pageSize): void
    {
        $this->currentPageSize = $pageSize;

        $this->recalculatePageDimensions();
    }

    private function recalculatePageDimensions(): void
    {
        if ($this->currentOrientation == PageOrientation::PORTRAIT) {
            $this->pageWidth = $this->currentPageSize->getWidth($this->units);
            $this->pageHeight = $this->currentPageSize->getHeight($this->units);
        }

        if ($this->currentOrientation == PageOrientation::LANDSCAPE) {
            $this->pageWidth = $this->currentPageSize->getHeight($this->units);
            $this->pageHeight = $this->currentPageSize->getWidth($this->units);
        }
    }

    private function closeDocument(): self
    {
        if ($this->currentDocumentState === DocumentState::CLOSED) {
            return $this;
        }

        $pdf = clone $this;

        if ($pdf->currentPageNumber === 0) {
            $pdf = $pdf->addPage();
        }

        $pdf->endPage();

        $pdf->appendHeaderIntoBuffer();
        $pdf->appendPagesIntoBuffer();
        $pdf->appendResourcesIntoBuffer();
        $pdf->appendMetadataIntoBuffer();
        $pdf->appendCatalogIntoBuffer();
        $offsetAtXRef = $pdf->currentBufferLength();
        $pdf->appendXRefIntoBuffer();
        $pdf->appendTrailerIntoBuffer((string) $offsetAtXRef);
        $pdf->currentDocumentState = DocumentState::CLOSED;

        return $pdf;
    }

    private function getStringWidth(string $string): float
    {
        if ($this->currentFont === null) {
            throw new IncorrectFontDefinitionException();
        }

        $characterWidths = $this->usedFonts[$this->currentFont::class]['cw'];
        $stringWidth = 0;
        $unicode = $this->utf8StringToUnicodeArray($string);
        foreach ($unicode as $char) {
            if (isset($characterWidths[2 * $char])) {
                $stringWidth += (ord($characterWidths[2 * $char]) << 8) + ord($characterWidths[2 * $char + 1]);

                continue;
            }

            $stringWidth += $this->usedFonts[$this->currentFont::class]['attributes']->getMissingWidth();
        }

        return $stringWidth * $this->currentFontSize / 1000;
    }

    private function enableCompressionIfAvailable(): void
    {
        if (function_exists('gzcompress')) {
            $this->compressionEnabled = true;
        }
    }

    private function checkOutput(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new HeadersAlreadySentException(
                'Headers already sent at ' . $file . ':' . $line
            );
        }

        $outputBufferLength = ob_get_length();

        if ($outputBufferLength === false) {
            throw new ContentBufferException('ob_get_length() returned false');
        }

        if ($outputBufferLength === 0) {
            return;
        }

        if ($this->outputBufferCanBeCleaned()) {
            ob_clean();

            return;
        }

        throw new ContentBufferException('Some data has already been output');
    }

    private function outputBufferCanBeCleaned(): bool
    {
        $outputBufferContent = ob_get_contents();

        if ($outputBufferContent === false) {
            throw new ContentBufferException('ob_get_contents() returned false');
        }

        return (bool) preg_match('/^(\xEF\xBB\xBF)?\s*$/', $outputBufferContent);
    }

    private function startNewPage(): void
    {
        ++$this->currentPageNumber;
        $this->rawPageData[$this->currentPageNumber] = '';
        $this->pageLinks[$this->currentPageNumber] = [];
        $this->currentDocumentState = DocumentState::PAGE_STARTED;
        $this->currentXPosition = $this->leftMargin;
        $this->currentYPosition = $this->topMargin;

        $pageRotation = $this->currentPageRotation;

        $this->recalculatePageDimensions();
        $this->pageInfo[$this->currentPageNumber]['size'] = [
            $this->pageWidthInPoints(),
            $this->pageHeightInPoints(),
        ];
        $this->pageInfo[$this->currentPageNumber]['rotation'] = $pageRotation;
    }

    private function pageWidthInPoints(): float
    {
        return $this->pageWidth * $this->scaleFactor;
    }

    private function pageHeightInPoints(): float
    {
        return $this->pageHeight * $this->scaleFactor;
    }

    private function endPage(): void
    {
        $this->currentDocumentState = DocumentState::PAGE_ENDED;
    }

    private function isAscii(string $s): bool
    {
        $nb = strlen($s);
        for ($i = 0; $i < $nb; ++$i) {
            if (ord($s[$i]) > 127) {
                return false;
            }
        }

        return true;
    }

    private function _httpencode(
        string $param,
        string $value,
    ): string {
        // Encode HTTP header field parameter
        if ($this->isAscii($value)) {
            return $param . '="' . $value . '"';
        }

        return $param . "*=UTF-8''" . rawurlencode($value);
    }

    private function _UTF8toUTF16(string $s): string
    {
        // Convert UTF-8 to UTF-16BE with BOM
        return "\xFE\xFF" . mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    }

    private function escapeSpecialCharacters(string $s): string
    {
        if (
            mb_strpos($s, '(') !== false
            || mb_strpos($s, ')') !== false
            || mb_strpos($s, '\\') !== false
            || mb_strpos($s, "\r") !== false
        ) {
            return str_replace(
                ['\\', '(', ')', "\r"],
                ['\\\\', '\(', '\)', '\r'],
                $s
            );
        }

        return $s;
    }

    private function _textstring(string $s): string
    {
        // Format a text string
        if (!$this->isAscii($s)) {
            $s = $this->_UTF8toUTF16($s);
        }

        return '(' . $this->escapeSpecialCharacters($s) . ')';
    }

    private function _dounderline(float $x, float $y, string $txt): string
    {
        if ($this->currentFont === null) {
            throw new IncorrectFontDefinitionException();
        }

        // Underline text
        $up = $this->usedFonts[$this->currentFont::class]['up'];
        $ut = $this->usedFonts[$this->currentFont::class]['ut'];
        $w = $this->getStringWidth($txt) + $this->wordSpacing * substr_count($txt, ' ');

        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->scaleFactor, ($this->pageHeight - ($y - $up / 1000 * $this->currentFontSize)) * $this->scaleFactor, $w * $this->scaleFactor, -$ut / 1000 * $this->currentFontSizeInPoints);
    }

    private function out(string $s): void
    {
        if ($this->currentDocumentState === DocumentState::NOT_INITIALIZED) {
            throw new NoPageHasBeenAddedException();
        }

        if ($this->currentDocumentState === DocumentState::CLOSED) {
            throw new TheDocumentIsClosedException();
        }

        if ($this->currentDocumentState === DocumentState::PAGE_STARTED) {
            $this->rawPageData[$this->currentPageNumber] .= $s . "\n";

            return;
        }

        $this->appendIntoBuffer($s);
    }

    private function appendIntoBuffer(string $s): void
    {
        $this->pdfFileBuffer .= $s . "\n";
    }

    private function currentBufferLength(): int
    {
        return strlen($this->pdfFileBuffer);
    }

    private function newObject(?int $n = null): void
    {
        if ($n === null) {
            $n = ++$this->currentObjectNumber;
        }
        $this->objectOffsets[$n] = $this->currentBufferLength();
        $this->appendIntoBuffer($n . ' 0 obj');
    }

    private function _putstream(string $data): void
    {
        $this->appendIntoBuffer('stream');
        $this->appendIntoBuffer($data);
        $this->appendIntoBuffer('endstream');
    }

    private function _putstreamobject(string $data): void
    {
        $entries = '';

        if ($this->compressionEnabled) {
            $data = $this->compressData($data);
            $entries = '/Filter /FlateDecode ';
        }

        $entries .= '/Length ' . strlen($data);

        $this->newObject();
        $this->appendIntoBuffer('<<' . $entries . '>>');
        $this->_putstream($data);
        $this->appendIntoBuffer('endobj');
    }

    private function compressData(string $data): string
    {
        $data = gzcompress($data);
        if ($data === false) {
            throw new CompressionException('gzcompress() returned false');
        }

        return $data;
    }

    private function _putlinks(int $n): void
    {
        foreach ($this->pageLinks[$n] as $pl) {
            $this->newObject();
            $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
            $s = '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
            if (is_string($pl[4])) {
                $s .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';
            } else {
                $l = $this->internalLinks[$pl[4]];
                $h = $this->pageInfo[$l[0]]['size'][1];
                $s .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', $this->pageInfo[$l[0]]['n'], $h - $l[1] * $this->scaleFactor);
            }
            $this->appendIntoBuffer($s);
            $this->appendIntoBuffer('endobj');
        }
    }

    private function _putpage(int $n): void
    {
        $this->newObject();
        $this->appendIntoBuffer('<</Type /Page');
        $this->appendIntoBuffer('/Parent 1 0 R');
        $this->appendIntoBuffer(sprintf(
            '/MediaBox [0 0 %.2F %.2F]',
            $this->pageInfo[$n]['size'][0],
            $this->pageInfo[$n]['size'][1],
        ));
        if ($this->pageInfo[$n]['rotation'] !== PageRotation::NONE) {
            $this->appendIntoBuffer('/Rotate ' . $this->pageInfo[$n]['rotation']->toInteger());
        }
        $this->appendIntoBuffer('/Resources 2 0 R');
        if (!empty($this->pageLinks[$n])) {
            $s = '/Annots [';
            foreach ($this->pageLinks[$n] as $pl) {
                if (array_key_exists(5, $pl) === false) {
                    throw new IncorrectPageLinksException();
                }
                $s .= $pl[5] . ' 0 R ';
            }
            $s .= ']';
            $this->appendIntoBuffer($s);
        }
        $this->appendIntoBuffer('/Contents ' . ($this->currentObjectNumber + 1) . ' 0 R>>');
        $this->appendIntoBuffer('endobj');
        if (!empty($this->aliasForTotalNumberOfPages)) {
            $alias = $this->utf8ToUtf16Be($this->aliasForTotalNumberOfPages);
            $r = $this->utf8ToUtf16Be((string) $this->currentPageNumber);
            $this->rawPageData[$n] = str_replace(
                $alias,
                $r,
                $this->rawPageData[$n]
            );
        }
        $this->_putstreamobject($this->rawPageData[$n]);
        $this->_putlinks($n);
    }

    private function appendPagesIntoBuffer(): void
    {
        $nb = $this->currentPageNumber;
        $n = $this->currentObjectNumber;
        for ($i = 1; $i <= $nb; ++$i) {
            $this->pageInfo[$i]['n'] = ++$n;
            ++$n;
            foreach ($this->pageLinks[$i] as &$pl) {
                $pl[5] = ++$n;
            }
            unset($pl);
        }
        for ($i = 1; $i <= $nb; ++$i) {
            $this->_putpage($i);
        }
        // Pages root
        $this->newObject(1);
        $this->appendIntoBuffer('<</Type /Pages');
        $kids = '/Kids [';
        for ($i = 1; $i <= $nb; ++$i) {
            $kids .= $this->pageInfo[$i]['n'] . ' 0 R ';
        }
        $kids .= ']';
        $this->appendIntoBuffer($kids);
        $this->appendIntoBuffer('/Count ' . $nb);
        $w = $this->pageInfo[1]['size'][0];
        $h = $this->pageInfo[1]['size'][1];
        $this->appendIntoBuffer(sprintf('/MediaBox [0 0 %.2F %.2F]', $w, $h));
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function appendFontsIntoBuffer(): void
    {
        foreach ($this->usedFonts as $k => $font) {
            $this->usedFonts[$k]['n'] = $this->currentObjectNumber + 1;

            $ttf = new TtfParser();
            $fontname = 'MPDFAA+' . $font['name'];
            $subset = $font['subset'];
            unset($subset[0]);
            $ttfontstream = $ttf->makeSubset($font['ttffile'], $subset);
            $ttfontsize = strlen($ttfontstream);
            $fontstream = gzcompress($ttfontstream);
            if ($fontstream === false) {
                throw new CompressionException('gzcompress() returned false');
            }
            $codeToGlyph = $ttf->codeToGlyph;
            unset($codeToGlyph[0]);

            // Type0 Font
            // A composite font - a font composed of other fonts, organized hierarchically
            $this->newObject();
            $this->appendIntoBuffer('<</Type /Font');
            $this->appendIntoBuffer('/Subtype /Type0');
            $this->appendIntoBuffer('/BaseFont /' . $fontname . '');
            $this->appendIntoBuffer('/Encoding /Identity-H');
            $this->appendIntoBuffer('/DescendantFonts [' . ($this->currentObjectNumber + 1) . ' 0 R]');
            $this->appendIntoBuffer('/ToUnicode ' . ($this->currentObjectNumber + 2) . ' 0 R');
            $this->appendIntoBuffer('>>');
            $this->appendIntoBuffer('endobj');

            // CIDFontType2
            // A CIDFont whose glyph descriptions are based on TrueType font technology
            $this->newObject();
            $this->appendIntoBuffer('<</Type /Font');
            $this->appendIntoBuffer('/Subtype /CIDFontType2');
            $this->appendIntoBuffer('/BaseFont /' . $fontname . '');
            $this->appendIntoBuffer('/CIDSystemInfo ' . ($this->currentObjectNumber + 2) . ' 0 R');
            $this->appendIntoBuffer('/FontDescriptor ' . ($this->currentObjectNumber + 3) . ' 0 R');
            $this->out('/DW ' . $font['attributes']->getMissingWidth() . '');

            $this->_putTTfontwidths($font, $ttf->maxUni);

            $this->appendIntoBuffer('/CIDToGIDMap ' . ($this->currentObjectNumber + 4) . ' 0 R');
            $this->appendIntoBuffer('>>');
            $this->appendIntoBuffer('endobj');

            // ToUnicode
            $this->newObject();
            $toUni = "/CIDInit /ProcSet findresource begin\n";
            $toUni .= "12 dict begin\n";
            $toUni .= "begincmap\n";
            $toUni .= "/CIDSystemInfo\n";
            $toUni .= "<</Registry (Adobe)\n";
            $toUni .= "/Ordering (UCS)\n";
            $toUni .= "/Supplement 0\n";
            $toUni .= ">> def\n";
            $toUni .= "/CMapName /Adobe-Identity-UCS def\n";
            $toUni .= "/CMapType 2 def\n";
            $toUni .= "1 begincodespacerange\n";
            $toUni .= "<0000> <FFFF>\n";
            $toUni .= "endcodespacerange\n";
            $toUni .= "1 beginbfrange\n";
            $toUni .= "<0000> <FFFF> <0000>\n";
            $toUni .= "endbfrange\n";
            $toUni .= "endcmap\n";
            $toUni .= "CMapName currentdict /CMap defineresource pop\n";
            $toUni .= "end\n";
            $toUni .= 'end';
            $this->appendIntoBuffer('<</Length ' . strlen($toUni) . '>>');
            $this->_putstream($toUni);
            $this->appendIntoBuffer('endobj');

            // CIDSystemInfo dictionary
            $this->newObject();
            $this->appendIntoBuffer('<</Registry (Adobe)');
            $this->appendIntoBuffer('/Ordering (UCS)');
            $this->appendIntoBuffer('/Supplement 0');
            $this->appendIntoBuffer('>>');
            $this->appendIntoBuffer('endobj');

            // Font descriptor
            $this->newObject();
            $this->appendIntoBuffer('<</Type /FontDescriptor');
            $this->appendIntoBuffer('/FontName /' . $fontname);
            foreach ($font['attributes']->toArray() as $kd => $v) {
                if ($kd == 'Flags') {
                    $v = $v | 4;
                    $v = $v & ~32;
                }    // SYMBOLIC font flag
                $this->out(' /' . $kd . ' ' . $v);
            }
            $this->appendIntoBuffer('/FontFile2 ' . ($this->currentObjectNumber + 2) . ' 0 R');
            $this->appendIntoBuffer('>>');
            $this->appendIntoBuffer('endobj');

            // Embed CIDToGIDMap
            // A specification of the mapping from CIDs to glyph indices
            $cidtogidmap = '';
            $cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
            foreach ($codeToGlyph as $cc => $glyph) {
                $cidtogidmap[$cc * 2] = chr($glyph >> 8);
                $cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xFF);
            }
            $cidtogidmap = gzcompress($cidtogidmap);
            if ($cidtogidmap === false) {
                throw new CompressionException('gzcompress() returned false');
            }
            $this->newObject();
            $this->appendIntoBuffer('<</Length ' . strlen($cidtogidmap) . '');
            $this->appendIntoBuffer('/Filter /FlateDecode');
            $this->appendIntoBuffer('>>');
            $this->_putstream($cidtogidmap);
            $this->appendIntoBuffer('endobj');

            // Font file
            $this->newObject();
            $this->appendIntoBuffer('<</Length ' . strlen($fontstream));
            $this->appendIntoBuffer('/Filter /FlateDecode');
            $this->appendIntoBuffer('/Length1 ' . $ttfontsize);
            $this->appendIntoBuffer('>>');
            $this->_putstream($fontstream);
            $this->appendIntoBuffer('endobj');
            unset($ttf);
        }
    }

    /**
     * @param array<mixed> $font
     */
    private function _putTTfontwidths(array $font, int $maxUni): void
    {
        $rangeid = 0;
        $range = [];
        $prevcid = -2;
        $prevwidth = -1;
        $interval = false;
        $startcid = 1;

        $cwlen = $maxUni + 1;

        $prevcid = null;
        $prevwidth = null;
        $range = [];
        $rangeid = null;
        $interval = null;

        // for each character
        for ($cid = $startcid; $cid < $cwlen; ++$cid) {
            if ((!isset($font['cw'][$cid * 2]) || !isset($font['cw'][$cid * 2 + 1]))
                || ($font['cw'][$cid * 2] == "\00" && $font['cw'][$cid * 2 + 1] == "\00")
            ) {
                continue;
            }

            $width = (ord($font['cw'][$cid * 2]) << 8) + ord($font['cw'][$cid * 2 + 1]);
            if ($width == 65535) {
                $width = 0;
            }
            if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
                continue;
            }
            if ($cid == ($prevcid + 1)) {
                if ($width == $prevwidth) {
                    if ($width == $range[$rangeid][0]) {
                        $range[$rangeid][] = $width;
                    } else {
                        array_pop($range[$rangeid]);
                        // new range
                        $rangeid = $prevcid;
                        $range[$rangeid] = [];
                        $range[$rangeid][] = $prevwidth;
                        $range[$rangeid][] = $width;
                    }
                    $interval = true;
                    $range[$rangeid]['interval'] = true;
                } else {
                    if ($interval) {
                        // new range
                        $rangeid = $cid;
                        $range[$rangeid] = [];
                        $range[$rangeid][] = $width;
                    } else {
                        $range[$rangeid][] = $width;
                    }
                    $interval = false;
                }
            } else {
                $rangeid = $cid;
                $range[$rangeid] = [];
                $range[$rangeid][] = $width;
                $interval = false;
            }
            $prevcid = $cid;
            $prevwidth = $width;
        }
        $prevk = -1;
        $nextk = -1;
        $prevint = false;
        foreach ($range as $k => $ws) {
            $cws = count($ws);
            if (($k == $nextk) and (!$prevint) and ((!isset($ws['interval'])) or ($cws < 4))) {
                if (isset($range[$k]['interval'])) {
                    unset($range[$k]['interval']);
                }
                $range[$prevk] = array_merge($range[$prevk], $range[$k]);
                unset($range[$k]);
            } else {
                $prevk = $k;
            }
            $nextk = (int) $k + $cws;
            if (isset($ws['interval'])) {
                if ($cws > 3) {
                    $prevint = true;
                } else {
                    $prevint = false;
                }
                unset($range[$k]['interval']);
                --$nextk;
            } else {
                $prevint = false;
            }
        }
        $w = '';
        foreach ($range as $k => $ws) {
            if (count(array_count_values($ws)) == 1) {
                $w .= ' ' . $k . ' ' . ((int) $k + count($ws) - 1) . ' ' . $ws[0];
            } else {
                $w .= ' ' . $k . ' [ ' . implode(' ', $ws) . ' ]' . "\n";
            }
        }
        $this->out('/W [' . $w . ' ]');
    }

    private function appendImagesIntoBuffer(): void
    {
        foreach (array_keys($this->usedImages) as $file) {
            $this->_putimage($this->usedImages[$file]);
            unset($this->usedImages[$file]['data'], $this->usedImages[$file]['smask']);
        }
    }

    /** @param array<mixed> $info */
    private function _putimage(&$info): void
    {
        $this->newObject();
        $info['n'] = $this->currentObjectNumber;
        $this->appendIntoBuffer('<</Type /XObject');
        $this->appendIntoBuffer('/Subtype /Image');
        $this->appendIntoBuffer('/Width ' . $info['w']);
        $this->appendIntoBuffer('/Height ' . $info['h']);
        if ($info['cs'] == 'Indexed') {
            $this->appendIntoBuffer('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->currentObjectNumber + 1) . ' 0 R]');
        } else {
            $this->appendIntoBuffer('/ColorSpace /' . $info['cs']);
            if ($info['cs'] == 'DeviceCMYK') {
                $this->appendIntoBuffer('/Decode [1 0 1 0 1 0 1 0]');
            }
        }
        $this->appendIntoBuffer('/BitsPerComponent ' . $info['bpc']);
        if (isset($info['f'])) {
            $this->appendIntoBuffer('/Filter /' . $info['f']);
        }
        if (isset($info['dp'])) {
            $this->appendIntoBuffer('/DecodeParms <<' . $info['dp'] . '>>');
        }
        if (isset($info['trns']) && $info['trns'] !== []) {
            $trns = '';
            for ($i = 0; $i < count($info['trns']); ++$i) {
                $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
            }
            $this->appendIntoBuffer('/Mask [' . $trns . ']');
        }
        if (isset($info['smask'])) {
            $this->appendIntoBuffer('/SMask ' . ($this->currentObjectNumber + 1) . ' 0 R');
        }
        $this->appendIntoBuffer('/Length ' . strlen($info['data']) . '>>');
        $this->_putstream($info['data']);
        $this->appendIntoBuffer('endobj');
        // Soft mask
        if (isset($info['smask'])) {
            $dp = '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $info['w'];
            $smask = [
                'w' => $info['w'],
                'h' => $info['h'],
                'cs' => 'DeviceGray',
                'bpc' => 8,
                'f' => $info['f'],
                'dp' => $dp,
                'data' => $info['smask'],
            ];
            $this->_putimage($smask);
        }
        // Palette
        if ($info['cs'] == 'Indexed') {
            $this->_putstreamobject($info['pal']);
        }
    }

    private function appendResourcesIntoBuffer(): void
    {
        $this->appendFontsIntoBuffer();
        $this->appendImagesIntoBuffer();
        $this->appendResourceDictionaryIntoBuffer();
    }

    private function appendResourceDictionaryIntoBuffer(): void
    {
        $this->newObject(2);
        $this->appendIntoBuffer('<<');
        $this->appendIntoBuffer('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->appendIntoBuffer('/Font <<');
        foreach ($this->usedFonts as $font) {
            $this->appendIntoBuffer('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('/XObject <<');
        foreach ($this->usedImages as $image) {
            $this->appendIntoBuffer('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function appendTrailerIntoBuffer(string $offsetAtXRef): void
    {
        $this->appendIntoBuffer('trailer');
        $this->appendIntoBuffer('<<');
        $this->appendIntoBuffer('/Size ' . ($this->currentObjectNumber + 1));
        $this->appendIntoBuffer('/Root ' . $this->currentObjectNumber . ' 0 R');
        $this->appendIntoBuffer('/Info ' . ($this->currentObjectNumber - 1) . ' 0 R');
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('startxref');
        $this->appendIntoBuffer($offsetAtXRef);
        $this->appendIntoBuffer('%%EOF');
    }

    private function appendHeaderIntoBuffer(): void
    {
        $this->appendIntoBuffer('%PDF-' . $this->pdfVersion);
    }

    private function appendMetadataIntoBuffer(): void
    {
        $this->newObject();
        $this->appendIntoBuffer('<<');

        $metadataAsArray = $this->metadata->toArray();

        foreach ($metadataAsArray as $key => $value) {
            $this->appendIntoBuffer('/' . $key . ' ' . $this->_textstring($value));
        }

        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function appendCatalogIntoBuffer(): void
    {
        $this->newObject();
        $this->appendIntoBuffer('<<');
        $this->appendIntoBuffer('/Type /Catalog');
        $this->appendIntoBuffer('/Pages 1 0 R');
        if ($this->layoutMode == 'single') {
            $this->appendIntoBuffer('/PageLayout /SinglePage');
        } elseif ($this->layoutMode == 'continuous') {
            $this->appendIntoBuffer('/PageLayout /OneColumn');
        } elseif ($this->layoutMode == 'two') {
            $this->appendIntoBuffer('/PageLayout /TwoColumnLeft');
        }
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function appendXRefIntoBuffer(): void
    {
        $this->appendIntoBuffer('xref');
        $this->appendIntoBuffer('0 ' . ($this->currentObjectNumber + 1));
        $this->appendIntoBuffer('0000000000 65535 f ');
        for ($i = 1; $i <= $this->currentObjectNumber; ++$i) {
            $this->appendIntoBuffer(sprintf('%010d 00000 n ', $this->objectOffsets[$i]));
        }
    }

    private function utf8ToUtf16Be(string $str): string
    {
        return mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
    }

    /**
     * @return array<int>
     */
    private function utf8StringToUnicodeArray(string $string): array
    {
        $out = [];
        $stringLength = strlen($string);
        for ($characterPosition = 0; $characterPosition < $stringLength; ++$characterPosition) {
            $asciiAsInteger = ord($string[$characterPosition]);

            $unicode = $this->getUnicode(
                $string,
                $characterPosition,
                $stringLength,
                $asciiAsInteger,
            );

            if ($unicode === null) {
                continue;
            }

            $out[] = $unicode;
        }

        return $out;
    }

    private function getUnicode(
        string $string,
        int $characterPosition,
        int $stringLength,
        int $asciiAsInteger,
    ): ?int {
        if ($asciiAsInteger <= 0x7F) {
            return $asciiAsInteger;
        }

        if ($asciiAsInteger < 0xC2) {
            return null;
        }

        if (($asciiAsInteger <= 0xDF) && ($characterPosition < $stringLength - 1)) {
            return ($asciiAsInteger & 0x1F) << 6 | (ord($string[++$characterPosition]) & 0x3F);
        }

        if (($asciiAsInteger <= 0xEF) && ($characterPosition < $stringLength - 2)) {
            return ($asciiAsInteger & 0x0F) << 12
                | (ord($string[++$characterPosition]) & 0x3F) << 6
                | (ord($string[++$characterPosition]) & 0x3F);
        }

        if (($asciiAsInteger <= 0xF4) && ($characterPosition < $stringLength - 3)) {
            return ($asciiAsInteger & 0x0F) << 18
                | (ord($string[++$characterPosition]) & 0x3F) << 12
                | (ord($string[++$characterPosition]) & 0x3F) << 6
                | (ord($string[++$characterPosition]) & 0x3F);
        }

        return null;
    }

    private function recalculatePageBreakThreshold(): void
    {
        $this->pageBreakThreshold = $this->pageHeight - $this->pageBreakMargin;
    }

    private function automaticPageBreak(): self
    {
        $pdf = clone $this;

        if (
            $pdf->currentYPosition + $pdf->withHeight > $pdf->pageBreakThreshold
            && $pdf->automaticPageBreaking
            && $pdf->currentYPosition !== $pdf->topMargin
        ) {
            $x = $pdf->currentXPosition;
            $ws = $pdf->wordSpacing;
            if ($ws > 0) {
                $pdf->wordSpacing = 0;
                $pdf->out('0 Tw');
            }
            $pdf = $pdf->addPage();
            $pdf->currentXPosition = $x;
            if ($ws > 0) {
                $pdf->wordSpacing = $ws;
                $pdf->out(sprintf('%.3F Tw', $ws * $this->scaleFactor));
            }
        }

        return $pdf;
    }

    private function getRectangleAttribute(bool $fill, mixed $border): string
    {
        if ($fill) {
            return ($border === 1) ? 'B' : 'f';
        }

        return 'S';
    }
}
