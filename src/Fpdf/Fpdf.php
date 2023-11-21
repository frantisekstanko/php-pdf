<?php

namespace Stanko\Fpdf;

use DateTimeImmutable;
use Exception;
use Stanko\Fpdf\Exception\CannotAddPageToClosedDocumentException;
use Stanko\Fpdf\Exception\CannotOpenImageFileException;
use Stanko\Fpdf\Exception\CompressionException;
use Stanko\Fpdf\Exception\ContentBufferException;
use Stanko\Fpdf\Exception\FileStreamException;
use Stanko\Fpdf\Exception\FontNotFoundException;
use Stanko\Fpdf\Exception\IncorrectFontDefinitionException;
use Stanko\Fpdf\Exception\IncorrectPageLinksException;
use Stanko\Fpdf\Exception\IncorrectPngFileException;
use Stanko\Fpdf\Exception\InterlacingNotSupportedException;
use Stanko\Fpdf\Exception\InvalidLayoutModeException;
use Stanko\Fpdf\Exception\InvalidZoomModeException;
use Stanko\Fpdf\Exception\MemoryStreamException;
use Stanko\Fpdf\Exception\NoPageHasBeenAddedException;
use Stanko\Fpdf\Exception\TheDocumentIsClosedException;
use Stanko\Fpdf\Exception\UnknownColorTypeException;
use Stanko\Fpdf\Exception\UnknownCompressionMethodException;
use Stanko\Fpdf\Exception\UnknownFilterMethodException;
use Stanko\Fpdf\Exception\UnpackException;
use Stanko\Fpdf\Exception\UnsupportedImageTypeException;

final class Fpdf
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
    private float $lastPrintedCellHeight = 0;
    private float $lineWidth;

    /** @var array<string, array{
     * i: int,
     * type: string,
     * name: string,
     * attributes: array<string, mixed>,
     * up: float,
     * ut: float,
     * cw: string,
     * ttffile: string,
     * subset: array<int, int>,
     * n: int,
     * }> */
    private array $usedFonts = [];

    private string $currentFontFamily = '';
    private string $currentFontStyle = '';
    private bool $isUnderline = false;

    /** @var array<mixed> */
    private array $currentFont;
    private float $currentFontSizeInPoints = 12;
    private float $currentFontSize;
    private string $drawColor = '0 G';
    private string $fillColor = '0 g';
    private string $textColor = '0 g';
    private bool $fillAndTextColorDiffer = false;
    private bool $transparencyEnabled = false;
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
    private bool $isDrawingHeader = false;
    private bool $isDrawingFooter = false;
    private ?string $aliasForTotalNumberOfPages = null;
    private float|string $zoomMode = 'default';
    private string $layoutMode = 'default';

    private Metadata $metadata;
    private string $pdfVersion = '1.3';

    public function __construct(
        PageSize $pageSize,
        PageOrientation $pageOrientation = PageOrientation::PORTRAIT,
        Units $units = Units::MILLIMETERS,
    ) {
        $this->currentDocumentState = DocumentState::NOT_INITIALIZED;

        $this->metadata = Metadata::empty();

        $this->scaleFactor = $units->getScaleFactor();
        $this->defaultPageSize = $pageSize;
        $this->currentPageSize = $pageSize;

        $this->defaultOrientation = $pageOrientation;
        $this->currentOrientation = $pageOrientation;

        if ($pageOrientation == PageOrientation::PORTRAIT) {
            $this->pageWidth = $pageSize->getWidth($this->scaleFactor);
            $this->pageHeight = $pageSize->getHeight($this->scaleFactor);
        }

        if ($pageOrientation == PageOrientation::LANDSCAPE) {
            $this->pageWidth = $pageSize->getHeight($this->scaleFactor);
            $this->pageHeight = $pageSize->getWidth($this->scaleFactor);
        }

        $this->pageWidthInPoints = $this->pageWidth * $this->scaleFactor;
        $this->pageHeightInPoints = $this->pageHeight * $this->scaleFactor;
        $this->currentPageRotation = PageRotation::NONE;
        $margin = 28.35 / $this->scaleFactor;
        $this->setLeftMargin($margin);
        $this->setTopMargin($margin);
        $this->interiorCellMargin = $margin / 10;
        $this->lineWidth = .567 / $this->scaleFactor;
        $this->enableAutomaticPageBreaking(2 * $margin);
        $this->enableCompressionIfAvailable();
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

    public function enableAutomaticPageBreaking(float $breakMargin = 0): void
    {
        $this->automaticPageBreaking = true;
        $this->pageBreakMargin = $breakMargin;
        $this->recalculatePageBreakThreshold();
    }

    public function disableAutomaticPageBreaking(): void
    {
        $this->automaticPageBreaking = false;
    }

    public function setZoom(float|string $zoom): void
    {
        if (
            $zoom == 'fullpage'
            || $zoom == 'fullwidth'
            || $zoom == 'real'
            || $zoom == 'default'
            || !is_string($zoom)
        ) {
            $this->zoomMode = $zoom;

            return;
        }

        throw new InvalidZoomModeException();
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

    public function AliasNbPages(string $alias = '{nb}'): void
    {
        $this->aliasForTotalNumberOfPages = $alias;
    }

    public function AddPage(
        ?PageOrientation $pageOrientation = null,
        ?PageSize $pageSize = null,
        ?PageRotation $pageRotation = null,
    ): void {
        if ($this->currentDocumentState === DocumentState::CLOSED) {
            throw new CannotAddPageToClosedDocumentException();
        }
        $family = $this->currentFontFamily;
        $style = $this->currentFontStyle . ($this->isUnderline ? 'U' : '');
        $fontsize = $this->currentFontSizeInPoints;
        $lw = $this->lineWidth;
        $dc = $this->drawColor;
        $fc = $this->fillColor;
        $tc = $this->textColor;
        $cf = $this->fillAndTextColorDiffer;
        if ($this->currentPageNumber > 0) {
            // Page footer
            $this->isDrawingFooter = true;
            $this->Footer();
            $this->isDrawingFooter = false;
            // Close page
            $this->_endpage();
        }
        $this->startPage($pageOrientation, $pageSize, $pageRotation);
        // Set line cap style to square
        $this->_out('2 J');
        // Set line width
        $this->lineWidth = $lw;
        $this->_out(sprintf('%.2F w', $lw * $this->scaleFactor));
        // Set font
        if ($family) {
            $this->SetFont($family, $style, $fontsize);
        }
        // Set colors
        $this->drawColor = $dc;
        if ($dc != '0 G') {
            $this->_out($dc);
        }
        $this->fillColor = $fc;
        if ($fc != '0 g') {
            $this->_out($fc);
        }
        $this->textColor = $tc;
        $this->fillAndTextColorDiffer = $cf;
        // Page header
        $this->isDrawingHeader = true;
        $this->Header();
        $this->isDrawingHeader = false;
        // Restore line width
        if ($this->lineWidth != $lw) {
            $this->lineWidth = $lw;
            $this->_out(sprintf('%.2F w', $lw * $this->scaleFactor));
        }
        // Restore font
        if ($family) {
            $this->SetFont($family, $style, $fontsize);
        }
        // Restore colors
        if ($this->drawColor != $dc) {
            $this->drawColor = $dc;
            $this->_out($dc);
        }
        if ($this->fillColor != $fc) {
            $this->fillColor = $fc;
            $this->_out($fc);
        }
        $this->textColor = $tc;
        $this->fillAndTextColorDiffer = $cf;
    }

    public function Header(): void
    {
        // To be implemented in your own inherited class
    }

    public function Footer(): void
    {
        // To be implemented in your own inherited class
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

    public function setFillColor(Color $color): void
    {
        if ($color->isBlack()) {
            $this->fillColor = sprintf('%.3F g', 0);

            return;
        }

        $this->fillColor = sprintf(
            '%.3F %.3F %.3F rg',
            $color->getRed() / 255,
            $color->getGreen() / 255,
            $color->getBlue() / 255,
        );

        $this->fillAndTextColorDiffer = ($this->fillColor !== $this->textColor);
        if ($this->currentPageNumber > 0) {
            $this->_out($this->fillColor);
        }
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

    public function addFont(
        string $fontName,
        string $ttfFile,
    ): void {
        if (isset($this->usedFonts[$fontName])) {
            return;
        }
        $ttfstat = stat($ttfFile);

        if ($ttfstat === false) {
            throw new FontNotFoundException($ttfFile);
        }

        $ttfParser = new TtfParser();
        $ttfParser->getMetrics($ttfFile);
        $charWidths = $ttfParser->charWidths;
        $name = (string) preg_replace('/[ ()]/', '', $ttfParser->fullName);

        $attributes = [
            'Ascent' => round($ttfParser->ascent),
            'Descent' => round($ttfParser->descent),
            'CapHeight' => round($ttfParser->capHeight),
            'Flags' => $ttfParser->flags,
            'FontBBox' => '[' . round($ttfParser->bbox[0]) . ' ' . round($ttfParser->bbox[1]) . ' ' . round($ttfParser->bbox[2]) . ' ' . round($ttfParser->bbox[3]) . ']',
            'ItalicAngle' => $ttfParser->italicAngle,
            'StemV' => round($ttfParser->stemV),
            'MissingWidth' => round($ttfParser->defaultWidth),
        ];

        $sbarr = range(0, 32);

        if ($this->aliasForTotalNumberOfPages !== null) {
            $sbarr = range(0, 57);
        }

        $fontType = 'TTF';
        $this->usedFonts[$fontName] = [
            'i' => count($this->usedFonts) + 1,
            'type' => $fontType,
            'name' => $name,
            'attributes' => $attributes,
            'up' => round($ttfParser->underlinePosition),
            'ut' => round($ttfParser->underlineThickness),
            'cw' => $charWidths,
            'ttffile' => $ttfFile,
            'subset' => $sbarr,
            'n' => 0,
        ];

        unset($charWidths, $ttfParser);
    }

    public function SetFont(string $family, string $style = '', float $size = 0): void
    {
        // Select a font; size given in points
        if ($family == '') {
            $family = $this->currentFontFamily;
        }

        $style = strtoupper($style);
        if (strpos($style, 'U') !== false) {
            $this->isUnderline = true;
            $style = str_replace('U', '', $style);
        } else {
            $this->isUnderline = false;
        }
        if ($style == 'IB') {
            $style = 'BI';
        }
        if ($size == 0) {
            $size = $this->currentFontSizeInPoints;
        }
        // Test if font is already selected
        if ($this->currentFontFamily == $family && $this->currentFontStyle == $style && $this->currentFontSizeInPoints == $size) {
            return;
        }

        // Test if font is already loaded
        $fontkey = $family . $style;
        if (!isset($this->usedFonts[$fontkey])) {
            $this->Error('Undefined font: ' . $family . ' ' . $style);
        }
        // Select it
        $this->currentFontFamily = $family;
        $this->currentFontStyle = $style;
        $this->currentFontSizeInPoints = $size;
        $this->currentFontSize = $size / $this->scaleFactor;
        $this->currentFont = &$this->usedFonts[$fontkey];
        if ($this->currentPageNumber > 0) {
            if (is_integer($this->currentFont['i']) === false) {
                throw new IncorrectFontDefinitionException();
            }
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->currentFont['i'], $this->currentFontSizeInPoints));
        }
    }

    public function SetFontSize(float $size): void
    {
        // Set font size in points
        if ($this->currentFontSizeInPoints == $size) {
            return;
        }
        $this->currentFontSizeInPoints = $size;
        $this->currentFontSize = $size / $this->scaleFactor;
        if ($this->currentPageNumber > 0) {
            if (is_integer($this->currentFont['i']) === false) {
                throw new IncorrectFontDefinitionException();
            }
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->currentFont['i'], $this->currentFontSizeInPoints));
        }
    }

    public function AddLink(): int
    {
        // Create a new internal link
        $n = count($this->internalLinks) + 1;
        $this->internalLinks[$n] = [0, 0];

        return $n;
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

    public function Link(float $x, float $y, float $w, float $h, mixed $link): void
    {
        // Put a link on the page
        $this->pageLinks[$this->currentPageNumber][] = [
            $x * $this->scaleFactor,
            $this->pageHeightInPoints - $y * $this->scaleFactor,
            $w * $this->scaleFactor,
            $h * $this->scaleFactor, $link,
        ];
    }

    public function Text(float $x, float $y, string $txt): void
    {
        // Output a string
        $txt = (string) $txt;
        if (!isset($this->currentFont)) {
            $this->Error('No font has been set');
        }
        $txt2 = '(' . $this->_escape($this->UTF8ToUTF16BE($txt, false)) . ')';
        foreach ($this->UTF8StringToArray($txt) as $uni) {
            $this->currentFont['subset'][$uni] = $uni;
        }
        $s = sprintf('BT %.2F %.2F Td %s Tj ET', $x * $this->scaleFactor, ($this->pageHeight - $y) * $this->scaleFactor, $txt2);
        if ($this->isUnderline && $txt != '') {
            $s .= ' ' . $this->_dounderline($x, $y, $txt);
        }
        if ($this->fillAndTextColorDiffer) {
            $s = 'q ' . $this->textColor . ' ' . $s . ' Q';
        }
        $this->_out($s);
    }

    public function Cell(
        float $w,
        float $h = 0,
        string $txt = '',
        mixed $border = 0,
        int $ln = 0,
        string $align = '',
        bool $fill = false,
        string $link = '',
    ): void {
        // Output a cell
        $txt = (string) $txt;
        $k = $this->scaleFactor;
        if (
            $this->currentYPosition + $h > $this->pageBreakThreshold
            && !$this->isDrawingHeader
            && !$this->isDrawingFooter
            && $this->automaticPageBreaking
            && $this->currentYPosition !== $this->topMargin
        ) {
            // Automatic page break
            $x = $this->currentXPosition;
            $ws = $this->wordSpacing;
            if ($ws > 0) {
                $this->wordSpacing = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage(
                $this->currentOrientation,
                $this->currentPageSize,
                $this->currentPageRotation
            );
            $this->currentXPosition = $x;
            if ($ws > 0) {
                $this->wordSpacing = $ws;
                $this->_out(sprintf('%.3F Tw', $ws * $k));
            }
        }
        if ($w == 0) {
            $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
        }
        $s = '';
        if ($fill || $border == 1) {
            if ($fill) {
                $op = ($border == 1) ? 'B' : 'f';
            } else {
                $op = 'S';
            }
            $s = sprintf('%.2F %.2F %.2F %.2F re %s ', $this->currentXPosition * $k, ($this->pageHeight - $this->currentYPosition) * $k, $w * $k, -$h * $k, $op);
        }
        if (is_string($border)) {
            $x = $this->currentXPosition;
            $y = $this->currentYPosition;
            if (strpos($border, 'L') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->pageHeight - $y) * $k, $x * $k, ($this->pageHeight - ($y + $h)) * $k);
            }
            if (strpos($border, 'T') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->pageHeight - $y) * $k, ($x + $w) * $k, ($this->pageHeight - $y) * $k);
            }
            if (strpos($border, 'R') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', ($x + $w) * $k, ($this->pageHeight - $y) * $k, ($x + $w) * $k, ($this->pageHeight - ($y + $h)) * $k);
            }
            if (strpos($border, 'B') !== false) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x * $k, ($this->pageHeight - ($y + $h)) * $k, ($x + $w) * $k, ($this->pageHeight - ($y + $h)) * $k);
            }
        }
        if ($txt !== '') {
            if (!isset($this->currentFont)) {
                $this->Error('No font has been set');
            }
            if ($align == 'R') {
                $dx = $w - $this->interiorCellMargin - $this->getStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($w - $this->getStringWidth($txt)) / 2;
            } else {
                $dx = $this->interiorCellMargin;
            }
            if ($this->fillAndTextColorDiffer) {
                $s .= 'q ' . $this->textColor . ' ';
            }
            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($this->wordSpacing) {
                foreach ($this->UTF8StringToArray($txt) as $uni) {
                    $this->currentFont['subset'][$uni] = $uni;
                }
                $space = $this->_escape($this->UTF8ToUTF16BE(' ', false));
                $s .= sprintf(
                    'BT 0 Tw %.2F %.2F Td [',
                    ($this->currentXPosition + $dx) * $k,
                    ($this->pageHeight - ($this->currentYPosition + .5 * $h + .3 * $this->currentFontSize)) * $k
                );
                $t = explode(' ', $txt);
                $numt = count($t);
                for ($i = 0; $i < $numt; ++$i) {
                    $tx = $t[$i];
                    $tx = '(' . $this->_escape($this->UTF8ToUTF16BE($tx, false)) . ')';
                    $s .= sprintf('%s ', $tx);
                    if (($i + 1) < $numt) {
                        $adj = - ($this->wordSpacing * $this->scaleFactor) * 1000 / $this->currentFontSizeInPoints;
                        $s .= sprintf('%d(%s) ', $adj, $space);
                    }
                }
                $s .= '] TJ';
                $s .= ' ET';
            } else {
                $txt2 = '(' . $this->_escape($this->UTF8ToUTF16BE($txt, false)) . ')';
                foreach ($this->UTF8StringToArray($txt) as $uni) {
                    $this->currentFont['subset'][$uni] = $uni;
                }
                $s .= sprintf(
                    'BT %.2F %.2F Td %s Tj ET',
                    ($this->currentXPosition + $dx) * $k,
                    ($this->pageHeight - ($this->currentYPosition + .5 * $h + .3 * $this->currentFontSize)) * $k,
                    $txt2
                );
            }
            if ($this->isUnderline) {
                $s .= ' ' . $this->_dounderline($this->currentXPosition + $dx, $this->currentYPosition + .5 * $h + .3 * $this->currentFontSize, $txt);
            }
            if ($this->fillAndTextColorDiffer) {
                $s .= ' Q';
            }
            if ($link) {
                $this->Link($this->currentXPosition + $dx, $this->currentYPosition + .5 * $h - .5 * $this->currentFontSize, $this->getStringWidth($txt), $this->currentFontSize, $link);
            }
        }
        if ($s) {
            $this->_out($s);
        }
        $this->lastPrintedCellHeight = $h;
        if ($ln > 0) {
            // Go to next line
            $this->currentYPosition += $h;
            if ($ln == 1) {
                $this->currentXPosition = $this->leftMargin;
            }
        } else {
            $this->currentXPosition += $w;
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
        if (!isset($this->currentFont)) {
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
                $this->Cell($w, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
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
                    $this->Cell($w, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
                } else {
                    if ($align == 'J') {
                        $this->wordSpacing = ($ns > 1) ? ($wmax - $ls) / ($ns - 1) : 0;
                        $this->_out(sprintf('%.3F Tw', $this->wordSpacing * $this->scaleFactor));
                    }
                    $this->Cell($w, $h, mb_substr($s, $j, $sep - $j, 'UTF-8'), $b, 2, $align, $fill);
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
        $this->Cell($w, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
        $this->currentXPosition = $this->leftMargin;
    }

    public function Write(float $h, string $txt, string $link = ''): void
    {
        // Output text in flowing mode
        if (!isset($this->currentFont)) {
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
                $this->Cell($w, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), 0, 2, '', false, $link);
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
                    $this->Cell($w, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), 0, 2, '', false, $link);
                } else {
                    $this->Cell($w, $h, mb_substr($s, $j, $sep - $j, 'UTF-8'), 0, 2, '', false, $link);
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
            $this->Cell($l, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), 0, 0, '', false, $link);
        }
    }

    public function Ln(float $h = null): void
    {
        // Line feed; default value is the last cell height
        $this->currentXPosition = $this->leftMargin;
        if ($h === null) {
            $this->currentYPosition += $this->lastPrintedCellHeight;
        } else {
            $this->currentYPosition += $h;
        }
    }

    public function Image(
        string $file,
        float $x = null,
        float $y = null,
        float $w = 0,
        float $h = 0,
        string $type = '',
        string $link = '',
    ): void {
        // Put an image on the page
        if ($file == '') {
            $this->Error('Image file name is empty');
        }
        if (!isset($this->usedImages[$file])) {
            // First use of this image, get info
            if ($type == '') {
                $pos = strrpos($file, '.');
                if (!$pos) {
                    $this->Error('Image file has no extension and no type was specified: ' . $file);
                }
                $type = substr($file, $pos + 1);
            }
            $type = strtolower($type);

            $info = $this->parseImage($file, $type);

            $info['i'] = count($this->usedImages) + 1;
            $this->usedImages[$file] = $info;
        } else {
            $info = $this->usedImages[$file];
        }

        // Automatic width and height calculation if needed
        if ($w == 0 && $h == 0) {
            // Put image at 96 dpi
            $w = -96;
            $h = -96;
        }
        if ($w < 0) {
            $w = -$info['w'] * 72 / $w / $this->scaleFactor;
        }
        if ($h < 0) {
            $h = -$info['h'] * 72 / $h / $this->scaleFactor;
        }
        if ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        }
        if ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        // Flowing mode
        if ($y === null) {
            if (
                $this->currentYPosition + $h > $this->pageBreakThreshold
                && !$this->isDrawingHeader
                && !$this->isDrawingFooter
                && $this->automaticPageBreaking
            ) {
                // Automatic page break
                $x2 = $this->currentXPosition;
                $this->AddPage(
                    $this->currentOrientation,
                    $this->currentPageSize,
                    $this->currentPageRotation
                );
                $this->currentXPosition = $x2;
            }
            $y = $this->currentYPosition;
            $this->currentYPosition += $h;
        }

        if ($x === null) {
            $x = $this->currentXPosition;
        }
        $this->_out(sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q', $w * $this->scaleFactor, $h * $this->scaleFactor, $x * $this->scaleFactor, ($this->pageHeight - ($y + $h)) * $this->scaleFactor, $info['i']));
        if ($link) {
            $this->Link($x, $y, $w, $h, $link);
        }
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
        bool $isUTF8 = false,
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
                    header('Content-Disposition: inline; ' . $this->_httpencode('filename', $name, $isUTF8));
                    header('Cache-Control: private, max-age=0, must-revalidate');
                    header('Pragma: public');
                }
                echo $this->pdfFileBuffer;

                break;

            case 'D':
                // Download file
                $this->_checkoutput();
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; ' . $this->_httpencode('filename', $name, $isUTF8));
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

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->metadata = $this->metadata->createdAt($createdAt);
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
            $this->AddPage();
        }

        // Page footer
        $this->isDrawingFooter = true;
        $this->Footer();
        $this->isDrawingFooter = false;
        // Close page
        $this->_endpage();
        // Close document
        $this->_enddoc();
    }

    private function getStringWidth(string $s): float
    {
        $characterWidths = $this->currentFont['cw'];
        $stringWidth = 0;
        $unicode = $this->UTF8StringToArray($s);
        foreach ($unicode as $char) {
            if (is_string($characterWidths) && isset($characterWidths[2 * $char])) {
                $stringWidth += (ord($characterWidths[2 * $char]) << 8) + ord($characterWidths[2 * $char + 1]);
            } elseif (is_array($characterWidths) && $char > 0 && $char < 128 && isset($characterWidths[chr($char)])) {
                $stringWidth += $characterWidths[chr($char)];
            } elseif (is_array($this->currentFont['attributes']) && isset($this->currentFont['attributes']['MissingWidth'])) {
                $stringWidth += $this->currentFont['attributes']['MissingWidth'];
            } elseif (isset($this->currentFont['MissingWidth'])) {
                $stringWidth += $this->currentFont['MissingWidth'];
            } else {
                $stringWidth += 500;
            }
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
        $this->currentFontFamily = '';

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

    private function _endpage(): void
    {
        $this->currentDocumentState = DocumentState::PAGE_ENDED;
    }

    private function _isascii(string $s): bool
    {
        // Test if string is ASCII
        $nb = strlen($s);
        for ($i = 0; $i < $nb; ++$i) {
            if (ord($s[$i]) > 127) {
                return false;
            }
        }

        return true;
    }

    private function _httpencode(string $param, string $value, bool $isUTF8): string
    {
        // Encode HTTP header field parameter
        if ($this->_isascii($value)) {
            return $param . '="' . $value . '"';
        }
        if (!$isUTF8) {
            $value = $this->_UTF8encode($value);
        }

        return $param . "*=UTF-8''" . rawurlencode($value);
    }

    private function _UTF8encode(string $s): string
    {
        // Convert ISO-8859-1 to UTF-8
        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    private function _UTF8toUTF16(string $s): string
    {
        // Convert UTF-8 to UTF-16BE with BOM
        return "\xFE\xFF" . mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    }

    private function _escape(string $s): string
    {
        // Escape special characters
        if (strpos($s, '(') !== false || strpos($s, ')') !== false || strpos($s, '\\') !== false || strpos($s, "\r") !== false) {
            return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
        }

        return $s;
    }

    private function _textstring(string $s): string
    {
        // Format a text string
        if (!$this->_isascii($s)) {
            $s = $this->_UTF8toUTF16($s);
        }

        return '(' . $this->_escape($s) . ')';
    }

    private function _dounderline(float $x, float $y, string $txt): string
    {
        // Underline text
        $up = $this->currentFont['up'];
        $ut = $this->currentFont['ut'];
        $w = $this->getStringWidth($txt) + $this->wordSpacing * substr_count($txt, ' ');

        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->scaleFactor, ($this->pageHeight - ($y - $up / 1000 * $this->currentFontSize)) * $this->scaleFactor, $w * $this->scaleFactor, -$ut / 1000 * $this->currentFontSizeInPoints);
    }

    /** @return array<mixed> */
    private function parseJpg(string $file): array
    {
        // Extract info from a JPEG file
        $a = getimagesize($file);
        if (!$a) {
            $this->Error('Missing or incorrect image file: ' . $file);
        }
        if ($a[2] != 2) {
            $this->Error('Not a JPEG file: ' . $file);
        }
        if (!isset($a['channels']) || $a['channels'] == 3) {
            $colspace = 'DeviceRGB';
        } elseif ($a['channels'] == 4) {
            $colspace = 'DeviceCMYK';
        } else {
            $colspace = 'DeviceGray';
        }
        $bpc = $a['bits'] ?? 8;
        $data = file_get_contents($file);

        return ['w' => $a[0], 'h' => $a[1], 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'DCTDecode', 'data' => $data];
    }

    /** @return array<mixed> */
    private function parsePng(string $file): array
    {
        // Extract info from a PNG file
        $f = fopen($file, 'rb');
        if (!$f) {
            $this->Error('Can\'t open image file: ' . $file);
        }
        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    /**
     * @param resource $f
     *
     * @return array<mixed>
     */
    private function _parsepngstream($f, string $file): array
    {
        // Check signature
        if ($this->_readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->Error('Not a PNG file: ' . $file);
        }

        // Read header chunk
        $this->_readstream($f, 4);

        $fileTypeByte = $this->_readstream($f, 4);
        if ($fileTypeByte != 'IHDR') {
            throw new IncorrectPngFileException($file);
        }

        $w = $this->_readint($f);
        $h = $this->_readint($f);
        $bpc = ord($this->_readstream($f, 1));
        if ($bpc > 8) {
            $this->Error('16-bit depth not supported: ' . $file);
        }
        $ct = ord($this->_readstream($f, 1));
        if ($ct == 0 || $ct == 4) {
            $colspace = 'DeviceGray';
        } elseif ($ct == 2 || $ct == 6) {
            $colspace = 'DeviceRGB';
        } elseif ($ct == 3) {
            $colspace = 'Indexed';
        } else {
            throw new UnknownColorTypeException();
        }

        $compressionByte = ord($this->_readstream($f, 1));
        if ($compressionByte != 0) {
            throw new UnknownCompressionMethodException($file);
        }

        $filterByte = ord($this->_readstream($f, 1));
        if ($filterByte != 0) {
            throw new UnknownFilterMethodException($file);
        }

        $interlacingByte = ord($this->_readstream($f, 1));
        if ($interlacingByte != 0) {
            throw new InterlacingNotSupportedException($file);
        }

        $this->_readstream($f, 4);
        $dp = '/Predictor 15 /Colors ' . ($colspace == 'DeviceRGB' ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w;

        // Scan chunks looking for palette, transparency and image data
        $pal = '';
        $trns = '';
        $data = '';
        do {
            $n = $this->_readint($f);
            $type = $this->_readstream($f, 4);
            if ($type == 'PLTE') {
                // Read palette
                $pal = $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type == 'tRNS') {
                // Read transparency info
                $t = $this->_readstream($f, $n);
                if ($ct == 0) {
                    $trns = [ord(substr($t, 1, 1))];
                } elseif ($ct == 2) {
                    $trns = [ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1))];
                } else {
                    $pos = strpos($t, chr(0));
                    if ($pos !== false) {
                        $trns = [$pos];
                    }
                }
                $this->_readstream($f, 4);
            } elseif ($type == 'IDAT') {
                // Read image data block
                $data .= $this->_readstream($f, $n);
                $this->_readstream($f, 4);
            } elseif ($type == 'IEND') {
                break;
            } else {
                $this->_readstream($f, $n + 4);
            }
        } while ($n);

        if ($colspace == 'Indexed' && $pal === '') {
            $this->Error('Missing palette in ' . $file);
        }
        $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'dp' => $dp, 'pal' => $pal, 'trns' => $trns];
        if ($ct >= 4) {
            // Extract alpha channel
            if (!function_exists('gzuncompress')) {
                $this->Error('Zlib not available, can\'t handle alpha channel: ' . $file);
            }
            $data = gzuncompress($data);
            if ($data === false) {
                throw new CompressionException('gzuncompress() returned false');
            }
            $color = '';
            $alpha = '';
            if ($ct == 4) {
                // Gray image
                $len = 2 * $w;
                for ($i = 0; $i < $h; ++$i) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.)./s', '$1', $line);
                    $alpha .= preg_replace('/.(.)/s', '$1', $line);
                }
            } else {
                // RGB image
                $len = 4 * $w;
                for ($i = 0; $i < $h; ++$i) {
                    $pos = (1 + $len) * $i;
                    $color .= $data[$pos];
                    $alpha .= $data[$pos];
                    $line = substr($data, $pos + 1, $len);
                    $color .= preg_replace('/(.{3})./s', '$1', $line);
                    $alpha .= preg_replace('/.{3}(.)/s', '$1', $line);
                }
            }
            unset($data);
            $data = gzcompress($color);
            $info['smask'] = gzcompress($alpha);
            $this->transparencyEnabled = true;
            if ($this->pdfVersion < '1.4') {
                $this->pdfVersion = '1.4';
            }
        }
        $info['data'] = $data;

        return $info;
    }

    /**
     * @param resource $f
     */
    private function _readstream($f, int $n): string
    {
        // Read n bytes from stream
        $res = '';
        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                throw new FileStreamException('fread() returned false');
            }
            $n -= strlen($s);
            $res .= $s;
        }
        if ($n > 0) {
            throw new FileStreamException('Unexpected end of stream');
        }

        return $res;
    }

    /**
     * @param resource $f
     */
    private function _readint($f): int
    {
        // Read a 4-byte integer from stream
        $a = unpack('Ni', $this->_readstream($f, 4));

        if ($a === false) {
            throw new UnpackException('unpack() returned false');
        }

        return $a['i'];
    }

    /** @return array<mixed> */
    private function parseGif(string $file): array
    {
        // Extract info from a GIF file (via PNG conversion)
        if (!function_exists('imagepng')) {
            $this->Error('GD extension is required for GIF support');
        }
        if (!function_exists('imagecreatefromgif')) {
            $this->Error('GD has no GIF read support');
        }
        $im = imagecreatefromgif($file);
        if ($im === false) {
            throw new CannotOpenImageFileException($file);
        }
        imageinterlace($im, false);
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        if ($data === false) {
            throw new ContentBufferException('ob_get_clean() returned false');
        }
        imagedestroy($im);
        $f = fopen('php://temp', 'rb+');
        if ($f === false) {
            throw new MemoryStreamException('fopen() returned false');
        }
        fwrite($f, $data);
        rewind($f);
        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
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

    private function _getoffset(): int
    {
        return strlen($this->pdfFileBuffer);
    }

    private function _newobj(?int $n = null): void
    {
        // Begin a new object
        if ($n === null) {
            $n = ++$this->currentObjectNumber;
        }
        $this->objectOffsets[$n] = $this->_getoffset();
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

        $this->_newobj();
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
            $this->_newobj();
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
        $this->_newobj();
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
        if ($this->transparencyEnabled) {
            $this->appendIntoBuffer('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
        }
        $this->appendIntoBuffer('/Contents ' . ($this->currentObjectNumber + 1) . ' 0 R>>');
        $this->appendIntoBuffer('endobj');
        // Page content
        if (!empty($this->aliasForTotalNumberOfPages)) {
            $alias = $this->UTF8ToUTF16BE($this->aliasForTotalNumberOfPages, false);
            $r = $this->UTF8ToUTF16BE((string) $this->currentPageNumber, false);
            $this->rawPageData[$n] = str_replace($alias, $r, $this->rawPageData[$n]);
            // Now repeat for no pages in non-subset fonts
            $this->rawPageData[$n] = str_replace($this->aliasForTotalNumberOfPages, (string) $this->currentPageNumber, $this->rawPageData[$n]);
        }
        $this->_putstreamobject($this->rawPageData[$n]);
        // Link annotations
        $this->_putlinks($n);
    }

    private function _putpages(): void
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
        $this->_newobj(1);
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
            $this->_newobj();
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
            $this->_newobj();
            $this->appendIntoBuffer('<</Type /Font');
            $this->appendIntoBuffer('/Subtype /CIDFontType2');
            $this->appendIntoBuffer('/BaseFont /' . $fontname . '');
            $this->appendIntoBuffer('/CIDSystemInfo ' . ($this->currentObjectNumber + 2) . ' 0 R');
            $this->appendIntoBuffer('/FontDescriptor ' . ($this->currentObjectNumber + 3) . ' 0 R');
            if (isset($font['attributes']['MissingWidth'])) {
                $this->_out('/DW ' . $font['attributes']['MissingWidth'] . '');
            }

            $this->_putTTfontwidths($font, $ttf->maxUni);

            $this->appendIntoBuffer('/CIDToGIDMap ' . ($this->currentObjectNumber + 4) . ' 0 R');
            $this->appendIntoBuffer('>>');
            $this->appendIntoBuffer('endobj');

            // ToUnicode
            $this->_newobj();
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
            $this->_newobj();
            $this->appendIntoBuffer('<</Registry (Adobe)');
            $this->appendIntoBuffer('/Ordering (UCS)');
            $this->appendIntoBuffer('/Supplement 0');
            $this->appendIntoBuffer('>>');
            $this->appendIntoBuffer('endobj');

            // Font descriptor
            $this->_newobj();
            $this->appendIntoBuffer('<</Type /FontDescriptor');
            $this->appendIntoBuffer('/FontName /' . $fontname);
            foreach ($font['attributes'] as $kd => $v) {
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
            $this->_newobj();
            $this->appendIntoBuffer('<</Length ' . strlen($cidtogidmap) . '');
            $this->appendIntoBuffer('/Filter /FlateDecode');
            $this->appendIntoBuffer('>>');
            $this->_putstream($cidtogidmap);
            $this->appendIntoBuffer('endobj');

            // Font file
            $this->_newobj();
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
        $this->_newobj();
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

    private function _putresources(): void
    {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->_newobj(2);
        $this->appendIntoBuffer('<<');
        $this->_putresourcedict();
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
    }

    private function _putinfo(): void
    {
        $metadataAsArray = $this->metadata->toArray();

        foreach ($metadataAsArray as $key => $value) {
            $this->appendIntoBuffer('/' . $key . ' ' . $this->_textstring($value));
        }
    }

    private function _putcatalog(): void
    {
        $n = $this->pageInfo[1]['n'];
        $this->appendIntoBuffer('/Type /Catalog');
        $this->appendIntoBuffer('/Pages 1 0 R');
        if ($this->zoomMode == 'fullpage') {
            $this->appendIntoBuffer('/OpenAction [' . $n . ' 0 R /Fit]');
        } elseif ($this->zoomMode == 'fullwidth') {
            $this->appendIntoBuffer('/OpenAction [' . $n . ' 0 R /FitH null]');
        } elseif ($this->zoomMode == 'real') {
            $this->appendIntoBuffer('/OpenAction [' . $n . ' 0 R /XYZ null null 1]');
        } elseif (!is_string($this->zoomMode)) {
            $this->appendIntoBuffer('/OpenAction [' . $n . ' 0 R /XYZ null null ' . sprintf('%.2F', $this->zoomMode / 100) . ']');
        }
        if ($this->layoutMode == 'single') {
            $this->appendIntoBuffer('/PageLayout /SinglePage');
        } elseif ($this->layoutMode == 'continuous') {
            $this->appendIntoBuffer('/PageLayout /OneColumn');
        } elseif ($this->layoutMode == 'two') {
            $this->appendIntoBuffer('/PageLayout /TwoColumnLeft');
        }
    }

    private function _putheader(): void
    {
        $this->appendIntoBuffer('%PDF-' . $this->pdfVersion);
    }

    private function _puttrailer(): void
    {
        $this->appendIntoBuffer('/Size ' . ($this->currentObjectNumber + 1));
        $this->appendIntoBuffer('/Root ' . $this->currentObjectNumber . ' 0 R');
        $this->appendIntoBuffer('/Info ' . ($this->currentObjectNumber - 1) . ' 0 R');
    }

    private function _enddoc(): void
    {
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        // Info
        $this->_newobj();
        $this->appendIntoBuffer('<<');
        $this->_putinfo();
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
        // Catalog
        $this->_newobj();
        $this->appendIntoBuffer('<<');
        $this->_putcatalog();
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('endobj');
        // Cross-ref
        $offset = $this->_getoffset();
        $this->appendIntoBuffer('xref');
        $this->appendIntoBuffer('0 ' . ($this->currentObjectNumber + 1));
        $this->appendIntoBuffer('0000000000 65535 f ');
        for ($i = 1; $i <= $this->currentObjectNumber; ++$i) {
            $this->appendIntoBuffer(sprintf('%010d 00000 n ', $this->objectOffsets[$i]));
        }
        // Trailer
        $this->appendIntoBuffer('trailer');
        $this->appendIntoBuffer('<<');
        $this->_puttrailer();
        $this->appendIntoBuffer('>>');
        $this->appendIntoBuffer('startxref');
        $this->appendIntoBuffer((string) $offset);
        $this->appendIntoBuffer('%%EOF');
        $this->currentDocumentState = DocumentState::CLOSED;
    }

    // ********* NEW FUNCTIONS *********
    // Converts UTF-8 strings to UTF16-BE.
    private function UTF8ToUTF16BE(string $str, bool $setbom = true): string
    {
        $outstr = '';
        if ($setbom) {
            $outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
        }
        $outstr .= mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');

        return $outstr;
    }

    // Converts UTF-8 strings to codepoints array
    /**
     * @return array<int>
     */
    private function UTF8StringToArray(string $str): array
    {
        $out = [];
        $len = strlen($str);
        for ($i = 0; $i < $len; ++$i) {
            $uni = -1;
            $h = ord($str[$i]);
            if ($h <= 0x7F) {
                $uni = $h;
            } elseif ($h >= 0xC2) {
                if (($h <= 0xDF) && ($i < $len - 1)) {
                    $uni = ($h & 0x1F) << 6 | (ord($str[++$i]) & 0x3F);
                } elseif (($h <= 0xEF) && ($i < $len - 2)) {
                    $uni = ($h & 0x0F) << 12 | (ord($str[++$i]) & 0x3F) << 6
                        | (ord($str[++$i]) & 0x3F);
                } elseif (($h <= 0xF4) && ($i < $len - 3)) {
                    $uni = ($h & 0x0F) << 18 | (ord($str[++$i]) & 0x3F) << 12
                        | (ord($str[++$i]) & 0x3F) << 6
                        | (ord($str[++$i]) & 0x3F);
                }
            }
            if ($uni >= 0) {
                $out[] = $uni;
            }
        }

        return $out;
    }

    /** @return array<mixed> */
    private function parseImage(string $file, string $type): array
    {
        if ($type === 'jpg' || $type == 'jpeg') {
            return $this->parseJpg($file);
        }
        if ($type === 'png') {
            return $this->parsePng($file);
        }
        if ($type === 'gif') {
            return $this->parseGif($file);
        }

        throw new UnsupportedImageTypeException();
    }

    private function recalculatePageBreakThreshold(): void
    {
        $this->pageBreakThreshold = $this->pageHeight - $this->pageBreakMargin;
    }
}
