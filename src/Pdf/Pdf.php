<?php

namespace Stanko\Pdf;

use DateTimeImmutable;
use Exception;
use Stanko\Pdf\Exception\CannotAddPageToClosedDocumentException;
use Stanko\Pdf\Exception\CannotOpenImageFileException;
use Stanko\Pdf\Exception\CompressionException;
use Stanko\Pdf\Exception\ContentBufferException;
use Stanko\Pdf\Exception\FontNotFoundException;
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
    private PageOrientation $defaultOrientation;
    private PageOrientation $currentOrientation;

    private PageSize $defaultPageSize;
    private PageSize $currentPageSize;

    private PageRotation $currentPageRotation;

    /** @var array<int, array{
     *   size: array<float>,
     *   rotation: PageRotation,
     *   n: int,
     * }>
     */
    private array $pageInfo = [];
    private float $pageWidthInPoints;
    private float $pageHeightInPoints;
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

    public function __construct()
    {
        $this->currentDocumentState = DocumentState::NOT_INITIALIZED;

        $this->metadata = Metadata::empty();

        $this->scaleFactor = Units::MILLIMETERS->getScaleFactor();

        $this->defaultOrientation = PageOrientation::PORTRAIT;
        $this->currentOrientation = PageOrientation::PORTRAIT;

        $this->setPageSize(PageSize::a4());

        $this->pageWidthInPoints = $this->pageWidth * $this->scaleFactor;
        $this->pageHeightInPoints = $this->pageHeight * $this->scaleFactor;
        $this->currentPageRotation = PageRotation::NONE;

        $margin = 28.35 / $this->scaleFactor;
        $this->setLeftMargin($margin);
        $this->setTopMargin($margin);
        $this->interiorCellMargin = $margin / 10;
        $this->lineWidth = .567 / $this->scaleFactor;

        $this->withAutomaticPageBreaking(2 * $margin);
        $this->enableCompressionIfAvailable();
    }

    public function inUnits(Units $units): self
    {
        $pdf = clone $this;

        $pdf->scaleFactor = $units->getScaleFactor();

        $pdf->recalculatePageDimensions();

        $margin = 28.35 / $pdf->scaleFactor;
        $pdf->setLeftMargin($margin);
        $pdf->setTopMargin($margin);
        $pdf->interiorCellMargin = $margin / 10;
        $pdf->lineWidth = .567 / $pdf->scaleFactor;

        return $pdf;
    }

    public function withPageOrientation(
        PageOrientation $pageOrientation,
    ): self {
        $pdf = clone $this;

        $pdf->defaultOrientation = $pageOrientation;
        $pdf->currentOrientation = $pageOrientation;

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

    public function withPageSize(PageSize $pageSize): self
    {
        $pdf = clone $this;

        $pdf->setPageSize($pageSize);

        return $pdf;
    }

    public function setLeftMargin(float $margin): void
    {
        $this->leftMargin = $margin;
        if ($this->currentPageNumber > 0 && $this->currentXPosition < $margin) {
            $this->currentXPosition = $margin;
        }
    }

    public function setTopMargin(float $margin): void
    {
        $this->topMargin = $margin;
    }

    public function setRightMargin(float $margin): void
    {
        $this->rightMargin = $margin;
    }

    public function withAutomaticPageBreaking(float $threshold = 0): void
    {
        $this->automaticPageBreaking = true;
        $this->pageBreakMargin = $threshold;
        $this->recalculatePageBreakThreshold();
    }

    public function disableAutomaticPageBreaking(): void
    {
        $this->automaticPageBreaking = false;
    }

    public function setLayout(string $layout = 'default'): void
    {
        if (
            $layout == 'single'
            || $layout == 'continuous'
            || $layout == 'two'
            || $layout == 'default'
        ) {
            $this->layoutMode = $layout;

            return;
        }

        throw new InvalidLayoutModeException();
    }

    public function enableCompression(): void
    {
        $this->enableCompressionIfAvailable();

        if ($this->compressionEnabled === false) {
            throw new CompressionException('gzcompress() is not available');
        }
    }

    public function disableCompression(): void
    {
        $this->compressionEnabled = false;
    }

    public function setTitle(string $title): void
    {
        $this->metadata = $this->metadata->withTitle($title);
    }

    public function setAuthor(string $author): void
    {
        $this->metadata = $this->metadata->withAuthor($author);
    }

    public function setSubject(string $subject): void
    {
        $this->metadata = $this->metadata->withSubject($subject);
    }

    public function setKeywords(string $keywords): void
    {
        $this->metadata = $this->metadata->withKeywords($keywords);
    }

    public function setCreator(string $creator): void
    {
        $this->metadata = $this->metadata->createdBy($creator);
    }

    public function setAliasForTotalNumberOfPages(string $alias = '{nb}'): void
    {
        $this->aliasForTotalNumberOfPages = $alias;
    }

    public function addPage(
        ?PageOrientation $pageOrientation = null,
        ?PageSize $pageSize = null,
        ?PageRotation $pageRotation = null,
    ): void {
        if ($this->currentDocumentState === DocumentState::CLOSED) {
            throw new CannotAddPageToClosedDocumentException();
        }
        $font = $this->currentFont;
        if ($this->currentPageNumber > 0) {
            $this->endPage();
        }
        $this->startPage($pageOrientation, $pageSize, $pageRotation);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->_out(sprintf('%.2F w', $this->lineWidth * $this->scaleFactor));
        if ($font) {
            $this->setFont($font);
        }
        if ($this->drawColor != '0 G') {
            $this->_out($this->drawColor);
        }
        if ($this->fillColor != '0 g') {
            $this->_out($this->fillColor);
        }
        if ($font) {
            $this->setFont($font);
        }
    }

    public function getCurrentPageNumber(): int
    {
        return $this->currentPageNumber;
    }

    public function setDrawColor(Color $color): void
    {
        if ($color->isBlack()) {
            $this->drawColor = sprintf('%.3F G', 0);

            return;
        }

        $this->drawColor = sprintf(
            '%.3F %.3F %.3F RG',
            $color->getRed() / 255,
            $color->getGreen() / 255,
            $color->getBlue() / 255,
        );

        if ($this->currentPageNumber > 0) {
            $this->_out($this->drawColor);
        }
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
            $pdf->_out($pdf->fillColor);
        }

        return $pdf;
    }

    public function setTextColor(Color $color): void
    {
        if ($color->isBlack()) {
            $this->textColor = sprintf('%.3F g', 0);

            return;
        }

        $this->textColor = sprintf(
            '%.3F %.3F %.3F rg',
            $color->getRed() / 255,
            $color->getGreen() / 255,
            $color->getBlue() / 255,
        );
        $this->fillAndTextColorDiffer = ($this->fillColor != $this->textColor);
    }

    public function setLineWidth(float $width): void
    {
        $this->lineWidth = $width;

        if ($this->currentPageNumber > 0) {
            $this->_out(sprintf('%.2F w', $width * $this->scaleFactor));
        }
    }

    public function drawLine(
        float $fromX,
        float $fromY,
        float $toX,
        float $toY,
    ): void {
        $this->_out(
            sprintf(
                '%.2F %.2F m %.2F %.2F l S',
                $fromX * $this->scaleFactor,
                ($this->pageHeight - $fromY) * $this->scaleFactor,
                $toX * $this->scaleFactor,
                ($this->pageHeight - $toY) * $this->scaleFactor
            )
        );
    }

    public function drawRectangle(
        float $xPosition,
        float $yPosition,
        float $width,
        float $height,
        RectangleStyle $style,
    ): void {
        $this->_out(
            sprintf(
                '%.2F %.2F %.2F %.2F re %s',
                $xPosition * $this->scaleFactor,
                ($this->pageHeight - $yPosition) * $this->scaleFactor,
                $width * $this->scaleFactor,
                -$height * $this->scaleFactor,
                $style->toPdfOperation(),
            )
        );
    }

    public function loadFont(
        FontInterface $font,
    ): self {
        if (isset($this->usedFonts[$font::class])) {
            return $this;
        }

        $ttfstat = stat($font->getTtfFilePath());

        if ($ttfstat === false) {
            throw new FontNotFoundException($font->getTtfFilePath());
        }

        $ttfParser = new TtfParser();
        $ttfParser->getMetrics($font->getTtfFilePath());
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
            'ttffile' => $font->getTtfFilePath(),
            'subset' => $sbarr,
            'n' => 0,
        ];

        return $pdf;
    }

    public function enableUnderline(): void
    {
        $this->isUnderline = true;
    }

    public function disableUnderline(): void
    {
        $this->isUnderline = false;
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

    public function Link(
        float $x,
        float $y,
        float $w,
        float $h,
        mixed $link,
    ): void {
        // Put a link on the page
        $this->pageLinks[$this->currentPageNumber][] = [
            $x * $this->scaleFactor,
            $this->pageHeightInPoints - $y * $this->scaleFactor,
            $w * $this->scaleFactor,
            $h * $this->scaleFactor, $link,
        ];
    }

    public function writeText(float $x, float $y, string $txt): void
    {
        if ($this->currentFont === null) {
            throw new NoFontHasBeenSetException();
        }

        $txt2 = '(' . $this->escapeSpecialCharacters(
            $this->utf8ToUtf16Be($txt)
        ) . ')';

        foreach ($this->utf8StringToArray($txt) as $uni) {
            $this->usedFonts[$this->currentFont::class]['subset'][$uni] = $uni;
        }
        $s = sprintf(
            'BT %.2F %.2F Td %s Tj ET',
            $x * $this->scaleFactor,
            ($this->pageHeight - $y) * $this->scaleFactor,
            $txt2,
        );
        if ($this->isUnderline && $txt != '') {
            $s .= ' ' . $this->_dounderline($x, $y, $txt);
        }
        if ($this->fillAndTextColorDiffer) {
            $s = 'q ' . $this->textColor . ' ' . $s . ' Q';
        }
        $this->_out($s);
    }

    public function drawCell(
        string $txt = '',
        mixed $border = 0,
        int $ln = 0,
        string $align = '',
        bool $fill = false,
        mixed $link = '',
    ): void {
        $this->automaticPageBreak();
        $cellWidth = $this->withWidth;
        if ($this->withWidth === null) {
            $cellWidth = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
        }
        $appendToPdfBuffer = '';
        if ($fill || $border === 1) {
            $appendToPdfBuffer = sprintf(
                '%.2F %.2F %.2F %.2F re %s ',
                $this->currentXPosition * $this->scaleFactor,
                ($this->pageHeight - $this->currentYPosition) * $this->scaleFactor,
                $cellWidth * $this->scaleFactor,
                -$this->withHeight * $this->scaleFactor,
                $this->getRectangleAttribute($fill, $border),
            );
        }
        if (is_string($border)) {
            $x = $this->currentXPosition;
            $y = $this->currentYPosition;
            if (strpos($border, 'L') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $this->scaleFactor,
                    ($this->pageHeight - $y) * $this->scaleFactor,
                    $x * $this->scaleFactor,
                    ($this->pageHeight - ($y + $this->withHeight)) * $this->scaleFactor
                );
            }
            if (strpos($border, 'T') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $this->scaleFactor,
                    ($this->pageHeight - $y) * $this->scaleFactor,
                    ($x + $cellWidth) * $this->scaleFactor,
                    ($this->pageHeight - $y) * $this->scaleFactor
                );
            }
            if (strpos($border, 'R') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    ($x + $cellWidth) * $this->scaleFactor,
                    ($this->pageHeight - $y) * $this->scaleFactor,
                    ($x + $cellWidth) * $this->scaleFactor,
                    ($this->pageHeight - ($y + $this->withHeight)) * $this->scaleFactor
                );
            }
            if (strpos($border, 'B') !== false) {
                $appendToPdfBuffer .= sprintf(
                    '%.2F %.2F m %.2F %.2F l S ',
                    $x * $this->scaleFactor,
                    ($this->pageHeight - ($y + $this->withHeight)) * $this->scaleFactor,
                    ($x + $cellWidth) * $this->scaleFactor,
                    ($this->pageHeight - ($y + $this->withHeight)) * $this->scaleFactor
                );
            }
        }
        if ($txt !== '') {
            if ($this->currentFont === null) {
                $this->Error('No font has been set');
            }
            if ($align == 'R') {
                $dx = $cellWidth - $this->interiorCellMargin - $this->getStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($cellWidth - $this->getStringWidth($txt)) / 2;
            } else {
                $dx = $this->interiorCellMargin;
            }
            if ($this->fillAndTextColorDiffer) {
                $appendToPdfBuffer .= 'q ' . $this->textColor . ' ';
            }
            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($this->wordSpacing) {
                foreach ($this->utf8StringToArray($txt) as $uni) {
                    $this->usedFonts[$this->currentFont::class]['subset'][$uni] = $uni;
                }
                $space = $this->escapeSpecialCharacters($this->utf8ToUtf16Be(' '));
                $appendToPdfBuffer .= sprintf(
                    'BT 0 Tw %.2F %.2F Td [',
                    ($this->currentXPosition + $dx) * $this->scaleFactor,
                    ($this->pageHeight - ($this->currentYPosition + .5 * $this->withHeight + .3 * $this->currentFontSize)) * $this->scaleFactor
                );
                $t = explode(' ', $txt);
                $numt = count($t);
                for ($i = 0; $i < $numt; ++$i) {
                    $tx = $t[$i];
                    $tx = '(' . $this->escapeSpecialCharacters($this->utf8ToUtf16Be($txt)) . ')';
                    $appendToPdfBuffer .= sprintf('%s ', $tx);
                    if (($i + 1) < $numt) {
                        $adj = - ($this->wordSpacing * $this->scaleFactor) * 1000 / $this->currentFontSizeInPoints;
                        $appendToPdfBuffer .= sprintf('%d(%s) ', $adj, $space);
                    }
                }
                $appendToPdfBuffer .= '] TJ';
                $appendToPdfBuffer .= ' ET';
            } else {
                $txt2 = '(' . $this->escapeSpecialCharacters($this->utf8ToUtf16Be($txt)) . ')';
                foreach ($this->utf8StringToArray($txt) as $uni) {
                    $this->usedFonts[$this->currentFont::class]['subset'][$uni] = $uni;
                }
                $appendToPdfBuffer .= sprintf(
                    'BT %.2F %.2F Td %s Tj ET',
                    ($this->currentXPosition + $dx) * $this->scaleFactor,
                    ($this->pageHeight - ($this->currentYPosition + .5 * $this->withHeight + .3 * $this->currentFontSize)) * $this->scaleFactor,
                    $txt2
                );
            }
            if ($this->isUnderline) {
                $appendToPdfBuffer .= ' ' . $this->_dounderline(
                    $this->currentXPosition + $dx,
                    $this->currentYPosition + .5 * $this->withHeight + .3 * $this->currentFontSize,
                    $txt,
                );
            }
            if ($this->fillAndTextColorDiffer) {
                $appendToPdfBuffer .= ' Q';
            }
            if ($link) {
                $this->Link(
                    $this->currentXPosition + $dx,
                    $this->currentYPosition + .5 * $this->withHeight - .5 * $this->currentFontSize,
                    $this->getStringWidth($txt),
                    $this->currentFontSize,
                    $link,
                );
            }
        }
        if ($appendToPdfBuffer) {
            $this->_out($appendToPdfBuffer);
        }
        $this->lastPrintedCellHeight = $this->withHeight;
        if ($ln > 0) {
            // Go to next line
            $this->currentYPosition += $this->withHeight;
            if ($ln == 1) {
                $this->currentXPosition = $this->leftMargin;
            }
        } else {
            $this->currentXPosition += $cellWidth;
        }
    }

    public function MultiCell(
        float $w,
        float $h,
        string $txt,
        int $border = 0,
        string $align = 'J',
        bool $fill = false,
    ): void {
        // Output text with automatic or explicit line breaks
        if ($this->currentFont === null) {
            $this->Error('No font has been set');
        }
        if ($w == 0) {
            $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
        }
        $wmax = ($w - 2 * $this->interiorCellMargin);
        // $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = mb_strlen($s, 'utf-8');
        while ($nb > 0 && mb_substr($s, $nb - 1, 1, 'utf-8') == "\n") {
            --$nb;
        }
        $b = 0;
        $b2 = '';
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos((string) $border, 'L') !== false) {
                    $b2 .= 'L';
                }
                if (strpos((string) $border, 'R') !== false) {
                    $b2 .= 'R';
                }
                $b = (strpos((string) $border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        $ls = 0;
        while ($i < $nb) {
            // Get next character
            $c = mb_substr($s, $i, 1, 'UTF-8');
            if ($c == "\n") {
                // Explicit line break
                if ($this->wordSpacing > 0) {
                    $this->wordSpacing = 0;
                    $this->_out('0 Tw');
                }
                $this->withWidth = $w;
                $this->withHeight = $h;

                $this->drawCell(mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
                ++$i;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                ++$nl;
                if ($border && $nl == 2) {
                    $b = $b2;
                }

                continue;
            }
            if ($c == ' ') {
                $sep = $i;
                $ls = $l;
                ++$ns;
            }

            $l += $this->getStringWidth($c);

            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($i == $j) {
                        ++$i;
                    }
                    if ($this->wordSpacing > 0) {
                        $this->wordSpacing = 0;
                        $this->_out('0 Tw');
                    }
                    $this->withWidth = $w;
                    $this->withHeight = $h;
                    $this->drawCell(mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->wordSpacing = ($ns > 1) ? ($wmax - $ls) / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->wordSpacing * $this->scaleFactor));
                    }
                    $this->withWidth = $w;
                    $this->withHeight = $h;

                    $this->drawCell(mb_substr($s, $j, $sep - $j, 'UTF-8'), $b, 2, $align, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                ++$nl;
                if ($border && $nl == 2) {
                    $b = $b2;
                }
            } else {
                ++$i;
            }
        }
        // Last chunk
        if ($this->wordSpacing > 0) {
            $this->wordSpacing = 0;
            $this->_out('0 Tw');
        }
        if ($border && strpos((string) $border, 'B') !== false) {
            $b .= 'B';
        }
        $this->withWidth = $w;
        $this->withHeight = $h;
        $this->drawCell(mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
        $this->currentXPosition = $this->leftMargin;
    }

    public function Write(float $h, string $txt, string $link = ''): void
    {
        // Output text in flowing mode
        if ($this->currentFont === null) {
            $this->Error('No font has been set');
        }
        $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
        $wmax = ($w - 2 * $this->interiorCellMargin);
        $s = str_replace("\r", '', (string) $txt);
        $nb = mb_strlen($s, 'UTF-8');
        if ($nb == 1 && $s == ' ') {
            $this->currentXPosition += $this->getStringWidth($s);

            return;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            // Get next character
            $c = mb_substr($s, $i, 1, 'UTF-8');
            if ($c == "\n") {
                // Explicit line break
                $this->withWidth = $w;
                $this->withHeight = $h;
                $this->drawCell(mb_substr($s, $j, $i - $j, 'UTF-8'), 0, 2, '', false, $link);
                ++$i;
                $sep = -1;
                $j = $i;
                $l = 0;
                if ($nl == 1) {
                    $this->currentXPosition = $this->leftMargin;
                    $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
                    $wmax = ($w - 2 * $this->interiorCellMargin);
                }
                ++$nl;

                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }

            $l += $this->getStringWidth($c);

            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($this->currentXPosition > $this->leftMargin) {
                        // Move to next line
                        $this->currentXPosition = $this->leftMargin;
                        $this->currentYPosition += $h;
                        $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
                        $wmax = ($w - 2 * $this->interiorCellMargin);
                        ++$i;
                        ++$nl;

                        continue;
                    }
                    if ($i == $j) {
                        ++$i;
                    }
                    $this->withWidth = $w;
                    $this->withHeight = $h;
                    $this->drawCell(mb_substr($s, $j, $i - $j, 'UTF-8'), 0, 2, '', false, $link);
                } else {
                    $this->drawCell(mb_substr($s, $j, $sep - $j, 'UTF-8'), 0, 2, '', false, $link);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                if ($nl == 1) {
                    $this->currentXPosition = $this->leftMargin;
                    $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
                    $wmax = ($w - 2 * $this->interiorCellMargin);
                }
                ++$nl;
            } else {
                ++$i;
            }
        }
        // Last chunk
        if ($i != $j) {
            $this->withWidth = $l;
            $this->withHeight = $h;

            $this->drawCell(mb_substr($s, $j, $i - $j, 'UTF-8'), 0, 0, '', false, $link);
        }
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
        float $xPosition = null,
        float $yPosition = null,
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
            // Put image at 96 dpi
            $imageWidth = -96;
            $imageHeight = -96;
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
            ) {
                // Automatic page break
                $x2 = $pdf->currentXPosition;
                $pdf->addPage(
                    $pdf->currentOrientation,
                    $pdf->currentPageSize,
                    $pdf->currentPageRotation
                );
                $pdf->currentXPosition = $x2;
            }
            $yPosition = $pdf->currentYPosition;
            $pdf->currentYPosition += $imageHeight;
        }

        if ($xPosition === null) {
            $xPosition = $pdf->currentXPosition;
        }
        $pdf->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $imageWidth * $pdf->scaleFactor, $imageHeight * $pdf->scaleFactor, $xPosition * $pdf->scaleFactor, ($pdf->pageHeight - ($yPosition + $imageHeight)) * $pdf->scaleFactor, $info['i']));
        if ($link) {
            $pdf->Link($xPosition, $yPosition, $imageWidth, $imageHeight, $link);
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

    public function GetX(): float
    {
        return $this->currentXPosition;
    }

    public function SetX(float $x): void
    {
        if ($x >= 0) {
            $this->currentXPosition = $x;
        } else {
            $this->currentXPosition = $this->pageWidth + $x;
        }
    }

    public function GetY(): float
    {
        return $this->currentYPosition;
    }

    public function SetY(float $y, bool $resetX = true): void
    {
        // Set y position and optionally reset x
        if ($y >= 0) {
            $this->currentYPosition = $y;
        } else {
            $this->currentYPosition = $this->pageHeight + $y;
        }
        if ($resetX) {
            $this->currentXPosition = $this->leftMargin;
        }
    }

    public function SetXY(float $x, float $y): void
    {
        // Set x and y positions
        $this->SetX($x);
        $this->SetY($y, false);
    }

    public function Output(
        string $dest = '',
        string $name = '',
    ): string {
        // Output PDF to some destination
        $this->Close();
        if (strlen($name) == 1 && strlen($dest) != 1) {
            // Fix parameter order
            $tmp = $dest;
            $dest = $name;
            $name = $tmp;
        }
        if ($dest == '') {
            $dest = 'I';
        }
        if ($name == '') {
            $name = 'doc.pdf';
        }

        switch (strtoupper($dest)) {
            case 'I':
                // Send to standard output
                $this->_checkoutput();
                if (PHP_SAPI != 'cli') {
                    // We send to a browser
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; ' . $this->_httpencode('filename', $name));
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->pdfFileBuffer;

                break;

            case 'D':
                // Download file
                $this->_checkoutput();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; ' . $this->_httpencode('filename', $name));
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
                echo $this->pdfFileBuffer;

                break;

            case 'F':
                // Save to local file
                if (!file_put_contents($name, $this->pdfFileBuffer)) {
                    $this->Error('Unable to create output file: ' . $name);
                }

                break;

            case 'S':
                // Return as a string
                return $this->pdfFileBuffer;

            default:
                $this->Error('Incorrect output destination: ' . $dest);
        }

        return '';
    }

    public function createdAt(DateTimeImmutable $createdAt): self
    {
        $pdf = clone $this;

        $pdf->metadata = $pdf->metadata->createdAt($createdAt);

        return $pdf;
    }

    private function setFont(
        FontInterface $font,
    ): void {
        if ($this->currentFont === $font) {
            return;
        }

        $this->currentFont = $font;
        $this->currentFontSizeInPoints = $font->getSizeInPoints();
        $this->currentFontSize = $font->getSizeInPoints() / $this->scaleFactor;

        if ($this->currentPageNumber > 0) {
            $this->writeFontInformationToDocument($font);
        }
    }

    private function writeFontInformationToDocument(
        FontInterface $font,
    ): void {
        $this->_out(
            sprintf(
                'BT /F%d %.2F Tf ET',
                $this->usedFonts[$font::class]['i'],
                $this->currentFontSizeInPoints,
            )
        );
    }

    private function setPageSize(PageSize $pageSize): void
    {
        $this->defaultPageSize = $pageSize;
        $this->currentPageSize = $pageSize;

        $this->recalculatePageDimensions();
    }

    private function recalculatePageDimensions(): void
    {
        if ($this->currentOrientation == PageOrientation::PORTRAIT) {
            $this->pageWidth = $this->currentPageSize->getWidth($this->scaleFactor);
            $this->pageHeight = $this->currentPageSize->getHeight($this->scaleFactor);
        }

        if ($this->currentOrientation == PageOrientation::LANDSCAPE) {
            $this->pageWidth = $this->currentPageSize->getHeight($this->scaleFactor);
            $this->pageHeight = $this->currentPageSize->getWidth($this->scaleFactor);
        }
    }

    private function Error(string $msg): never
    {
        throw new Exception('tFPDF error: ' . $msg);
    }

    private function Close(): void
    {
        if ($this->currentDocumentState == DocumentState::CLOSED) {
            return;
        }

        if ($this->currentPageNumber == 0) {
            $this->addPage();
        }

        $this->endPage();
        $this->closeDocument();
    }

    private function getStringWidth(string $s): float
    {
        if ($this->currentFont === null) {
            throw new IncorrectFontDefinitionException();
        }

        $characterWidths = $this->usedFonts[$this->currentFont::class]['cw'];
        $stringWidth = 0;
        $unicode = $this->utf8StringToArray($s);
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

    private function _checkoutput(): void
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->Error("Some data has already been output, can't send PDF file (output started at {$file}:{$line})");
            }
        }
        if (ob_get_length()) {
            // The output buffer is not empty
            $outputBufferContent = ob_get_contents();
            if ($outputBufferContent === false) {
                throw new ContentBufferException('ob_get_contents() returned false');
            }
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', $outputBufferContent)) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            } else {
                $this->Error("Some data has already been output, can't send PDF file");
            }
        }
    }

    private function startPage(
        ?PageOrientation $pageOrientation,
        ?PageSize $pageSize,
        ?PageRotation $pageRotation,
    ): void {
        ++$this->currentPageNumber;
        $this->rawPageData[$this->currentPageNumber] = '';
        $this->pageLinks[$this->currentPageNumber] = [];
        $this->currentDocumentState = DocumentState::PAGE_STARTED;
        $this->currentXPosition = $this->leftMargin;
        $this->currentYPosition = $this->topMargin;
        $this->currentFont = null;

        if ($pageOrientation === null) {
            $pageOrientation = $this->defaultOrientation;
        }

        if ($pageSize === null) {
            $pageSize = $this->defaultPageSize;
        }

        if ($pageRotation === null) {
            $pageRotation = PageRotation::NONE;
        }

        if (
            $pageOrientation !== $this->currentOrientation
            || $pageSize->getWidth($this->scaleFactor) != $this->currentPageSize->getWidth($this->scaleFactor)
            || $pageSize->getHeight($this->scaleFactor) != $this->currentPageSize->getHeight($this->scaleFactor)
        ) {
            if ($pageOrientation === PageOrientation::PORTRAIT) {
                $this->pageWidth = $pageSize->getWidth($this->scaleFactor);
                $this->pageHeight = $pageSize->getHeight($this->scaleFactor);
            } else {
                $this->pageWidth = $pageSize->getHeight($this->scaleFactor);
                $this->pageHeight = $pageSize->getWidth($this->scaleFactor);
            }
            $this->pageWidthInPoints = $this->pageWidth * $this->scaleFactor;
            $this->pageHeightInPoints = $this->pageHeight * $this->scaleFactor;
            $this->recalculatePageBreakThreshold();
            $this->currentOrientation = $pageOrientation;
            $this->currentPageSize = $pageSize;
        }
        if (
            $pageOrientation != $this->defaultOrientation
            || $pageSize->getWidth($this->scaleFactor) != $this->defaultPageSize->getWidth($this->scaleFactor)
            || $pageSize->getHeight($this->scaleFactor) != $this->defaultPageSize->getHeight($this->scaleFactor)
        ) {
            $this->pageInfo[$this->currentPageNumber]['size'] = [$this->pageWidthInPoints, $this->pageHeightInPoints];
        }

        $this->pageInfo[$this->currentPageNumber]['rotation'] = $pageRotation;
        $this->currentPageRotation = $pageRotation;
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
                ['\\\\', '\\(', '\\)', '\\r'],
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

    private function _out(string $s): void
    {
        if ($this->currentDocumentState === DocumentState::PAGE_STARTED) {
            $this->rawPageData[$this->currentPageNumber] .= $s . "\n";

            return;
        }

        if ($this->currentDocumentState === DocumentState::PAGE_ENDED) {
            $this->appendIntoBuffer($s);

            return;
        }

        if ($this->currentDocumentState === DocumentState::NOT_INITIALIZED) {
            throw new NoPageHasBeenAddedException();
        }

        if ($this->currentDocumentState === DocumentState::CLOSED) {
            throw new TheDocumentIsClosedException();
        }
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
                if (isset($this->pageInfo[$l[0]]['size'])) {
                    $h = $this->pageInfo[$l[0]]['size'][1];
                } else {
                    $h = ($this->defaultOrientation === PageOrientation::PORTRAIT) ?
                        $this->defaultPageSize->getHeight($this->scaleFactor) * $this->scaleFactor :
                        $this->defaultPageSize->getWidth($this->scaleFactor) * $this->scaleFactor;
                }
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
        if (isset($this->pageInfo[$n]['size'])) {
            $this->appendIntoBuffer(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->pageInfo[$n]['size'][0], $this->pageInfo[$n]['size'][1]));
        }
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
        // Page content
        if (!empty($this->aliasForTotalNumberOfPages)) {
            $alias = $this->utf8ToUtf16Be($this->aliasForTotalNumberOfPages);
            $r = $this->utf8ToUtf16Be((string) $this->currentPageNumber);
            $this->rawPageData[$n] = str_replace($alias, $r, $this->rawPageData[$n]);
            // Now repeat for no pages in non-subset fonts
            $this->rawPageData[$n] = str_replace($this->aliasForTotalNumberOfPages, (string) $this->currentPageNumber, $this->rawPageData[$n]);
        }
        $this->_putstreamobject($this->rawPageData[$n]);
        // Link annotations
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
        if ($this->defaultOrientation === PageOrientation::PORTRAIT) {
            $w = $this->defaultPageSize->getWidth($this->scaleFactor);
            $h = $this->defaultPageSize->getHeight($this->scaleFactor);
        } else {
            $w = $this->defaultPageSize->getHeight($this->scaleFactor);
            $h = $this->defaultPageSize->getWidth($this->scaleFactor);
        }
        $this->appendIntoBuffer(sprintf('/MediaBox [0 0 %.2F %.2F]', $w * $this->scaleFactor, $h * $this->scaleFactor));
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function _putfonts(): void
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
            $this->_out('/DW ' . $font['attributes']->getMissingWidth() . '');

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
                $this->_out(' /' . $kd . ' ' . $v);
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
            if (!isset($font['dw']) || ($width != $font['dw'])) {
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
        $this->_out('/W [' . $w . ' ]');
    }

    private function _putimages(): void
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
        if (isset($info['trns']) && is_array($info['trns'])) {
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
            $smask = ['w' => $info['w'], 'h' => $info['h'], 'cs' => 'DeviceGray', 'bpc' => 8, 'f' => $info['f'], 'dp' => $dp, 'data' => $info['smask']];
            $this->_putimage($smask);
        }
        // Palette
        if ($info['cs'] == 'Indexed') {
            $this->_putstreamobject($info['pal']);
        }
    }

    private function _putxobjectdict(): void
    {
        foreach ($this->usedImages as $image) {
            $this->appendIntoBuffer('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
    }

    private function _putresourcedict(): void
    {
        $this->appendIntoBuffer('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->appendIntoBuffer('/Font <<');
        foreach ($this->usedFonts as $font) {
            $this->appendIntoBuffer('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('/XObject <<');
        $this->_putxobjectdict();
        $this->appendIntoBuffer('>>');
    }

    private function appendResourcesIntoBuffer(): void
    {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->newObject(2);
        $this->appendIntoBuffer('<<');
        $this->_putresourcedict();
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function _putcatalog(): void
    {
        $this->appendIntoBuffer('/Type /Catalog');
        $this->appendIntoBuffer('/Pages 1 0 R');
        if ($this->layoutMode == 'single') {
            $this->appendIntoBuffer('/PageLayout /SinglePage');
        } elseif ($this->layoutMode == 'continuous') {
            $this->appendIntoBuffer('/PageLayout /OneColumn');
        } elseif ($this->layoutMode == 'two') {
            $this->appendIntoBuffer('/PageLayout /TwoColumnLeft');
        }
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

    private function closeDocument(): void
    {
        $this->appendHeaderIntoBuffer();
        $this->appendPagesIntoBuffer();
        $this->appendResourcesIntoBuffer();
        $this->appendMetadataIntoBuffer();
        $this->appendCatalogIntoBuffer();
        $offsetAtXRef = $this->currentBufferLength();
        $this->appendXRefIntoBuffer();
        $this->appendTrailerIntoBuffer((string) $offsetAtXRef);
        $this->currentDocumentState = DocumentState::CLOSED;
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
        $this->_putcatalog();
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
    private function utf8StringToArray(string $string): array
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

    private function automaticPageBreak(): void
    {
        if (
            $this->currentYPosition + $this->withHeight > $this->pageBreakThreshold
            && $this->automaticPageBreaking
            && $this->currentYPosition !== $this->topMargin
        ) {
            $x = $this->currentXPosition;
            $ws = $this->wordSpacing;
            if ($ws > 0) {
                $this->wordSpacing = 0;
                $this->_out('0 Tw');
            }
            $this->addPage(
                $this->currentOrientation,
                $this->currentPageSize,
                $this->currentPageRotation
            );
            $this->currentXPosition = $x;
            if ($ws > 0) {
                $this->wordSpacing = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $this->scaleFactor));
            }
        }
    }

    private function getRectangleAttribute(bool $fill, mixed $border): string
    {
        if ($fill) {
            return ($border === 1) ? 'B' : 'f';
        }

        return 'S';
    }
}
