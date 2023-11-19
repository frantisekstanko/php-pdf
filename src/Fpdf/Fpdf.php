<?php
/*
* tFPDF (based on FPDF 1.85)                                                   *
*                                                                              *
* Version:  1.33                                                               *
* Date:     2022-12-20                                                         *
* Authors:  Ian Back <ianb@bpm1.com>                                           *
*           Tycho Veltmeijer <tfpdf@tychoveltmeijer.nl> (versions 1.30+)       *
* License:  LGPL                                                               *
*/

namespace Stanko\Fpdf;

use DateTimeImmutable;
use Exception;
use Stanko\Fpdf\Exception\CreatedAtIsNotSetException;

class Fpdf
{
    public const VERSION = '1.33';
    protected int $currentPage;
    protected int $currentObjectNumber;

    /** @var array<int, int> */
    protected array $objectOffsets;
    protected string $pdfFileBuffer;

    /** @var array<int, string> */
    protected array $pages;
    protected int $currentDocumentState;
    protected bool $compressionEnabled;
    protected float $scaleFactor;
    protected string $defaultOrientation;
    protected string $currentOrientation;
    protected $StdPageSizes;       // standard page sizes

    /** @var array<mixed> */
    protected array $defaultPageSize;

    /** @var array<mixed> */
    protected array $currentPageSize;

    protected int $currentPageOrientation;

    /** @var array<int, array{
     *   size: array<float>,
     *   rotation: int,
     *   n: int,
     * }>
     */
    protected array $pageInfo;
    protected float $pageWidthInPoints;
    protected float $pageHeightInPoints;
    protected float $pageWidth;
    protected float $pageHeight;
    protected float $leftMargin;
    protected float $topMargin;
    protected float $rightMargin;
    protected float $pageBreakMargin;
    protected float $cellMargin;
    protected float $currentXPosition;
    protected float $currentYPosition;
    protected float $lastPrintedCellHeight;
    protected float $lineWidth;
    protected string $fontPath;

    /** @var array<string, array<mixed>> */
    protected array $usedFonts;

    /** @var array<string, array<mixed>> */
    protected array $fontFiles;

    /** @var array<mixed> */
    protected array $encodings;

    /** @var array<mixed> */
    protected array $cmaps;              // array of ToUnicode CMaps
    protected string $currentFontFamily;
    protected string $currentFontStyle;
    protected bool $isUnderline;

    /** @var array<mixed> */
    protected array $currentFont;
    protected float $currentFontSizeInPoints;
    protected float $currentFontSize;
    protected string $drawColor;
    protected string $fillColor;
    protected string $textColor;
    protected bool $fillColorEqualsTextColor;
    protected bool $transparencyEnabled;
    protected float $wordSpacing;

    /** @var array<string, array<mixed>> */
    protected array $usedImages;

    /** @var array<int, array<int, array{
     *  0: float,
     *  1: float,
     *  2: float,
     *  3: float,
     *  4: mixed,
     *  5?: int,
     * }>> */
    protected array $PageLinks;          // array of links in pages

    /** @var array<int, array{0: int, 1: float}> */
    protected array $links;              // array of internal links
    protected bool $AutoPageBreak;      // automatic page breaking
    protected float $PageBreakTrigger;   // threshold used to trigger page breaks
    protected bool $InHeader;           // flag set when processing header
    protected bool $InFooter;           // flag set when processing footer
    protected string $AliasNbPages;       // alias for total number of pages
    protected float|string $ZoomMode;           // zoom display mode
    protected string $LayoutMode;         // layout display mode

    /** @var array<mixed> */
    protected array $metadata;           // document properties
    protected ?DateTimeImmutable $createdAt = null;
    protected string $PDFVersion;         // PDF version number

    // Public methods

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        // Some checks
        $this->_dochecks();
        // Initialization of properties
        $this->currentDocumentState = 0;
        $this->currentPage = 0;
        $this->currentObjectNumber = 2;
        $this->pdfFileBuffer = '';
        $this->pages = [];
        $this->pageInfo = [];
        $this->usedFonts = [];
        $this->fontFiles = [];
        $this->encodings = [];
        $this->cmaps = [];
        $this->usedImages = [];
        $this->links = [];
        $this->InHeader = false;
        $this->InFooter = false;
        $this->lastPrintedCellHeight = 0;
        $this->currentFontFamily = '';
        $this->currentFontStyle = '';
        $this->currentFontSizeInPoints = 12;
        $this->isUnderline = false;
        $this->drawColor = '0 G';
        $this->fillColor = '0 g';
        $this->textColor = '0 g';
        $this->fillColorEqualsTextColor = false;
        $this->transparencyEnabled = false;
        $this->wordSpacing = 0;
        // Scale factor
        if ($unit == 'pt') {
            $this->scaleFactor = 1;
        } elseif ($unit == 'mm') {
            $this->scaleFactor = 72 / 25.4;
        } elseif ($unit == 'cm') {
            $this->scaleFactor = 72 / 2.54;
        } elseif ($unit == 'in') {
            $this->scaleFactor = 72;
        } else {
            $this->Error('Incorrect unit: ' . $unit);
        }
        // Page sizes
        $this->StdPageSizes = ['a3' => [841.89, 1190.55], 'a4' => [595.28, 841.89], 'a5' => [420.94, 595.28],
            'letter' => [612, 792], 'legal' => [612, 1008]];
        $size = $this->_getpagesize($size);
        $this->defaultPageSize = $size;
        $this->currentPageSize = $size;
        // Page orientation
        $orientation = strtolower($orientation);
        if ($orientation == 'p' || $orientation == 'portrait') {
            $this->defaultOrientation = 'P';
            $this->pageWidth = $size[0];
            $this->pageHeight = $size[1];
        } elseif ($orientation == 'l' || $orientation == 'landscape') {
            $this->defaultOrientation = 'L';
            $this->pageWidth = $size[1];
            $this->pageHeight = $size[0];
        } else {
            $this->Error('Incorrect orientation: ' . $orientation);
        }
        $this->currentOrientation = $this->defaultOrientation;
        $this->pageWidthInPoints = $this->pageWidth * $this->scaleFactor;
        $this->hPt = $this->pageHeight * $this->scaleFactor;
        // Page rotation
        $this->currentPageOrientation = 0;
        // Page margins (1 cm)
        $margin = 28.35 / $this->scaleFactor;
        $this->SetMargins($margin, $margin);
        // Interior cell margin (1 mm)
        $this->cellMargin = $margin / 10;
        // Line width (0.2 mm)
        $this->lineWidth = .567 / $this->scaleFactor;
        // Automatic page break
        $this->SetAutoPageBreak(true, 2 * $margin);
        // Default display mode
        $this->SetDisplayMode('default');
        // Enable compression
        $this->SetCompression(true);
        // Metadata
        $this->metadata = ['Producer' => 'tFPDF ' . self::VERSION];
        // Set default PDF version number
        $this->PDFVersion = '1.3';
    }

    public function SetMargins($left, $top, $right = null)
    {
        // Set left, top and right margins
        $this->leftMargin = $left;
        $this->topMargin = $top;
        if ($right === null) {
            $right = $left;
        }
        $this->rightMargin = $right;
    }

    public function SetLeftMargin($margin)
    {
        // Set left margin
        $this->leftMargin = $margin;
        if ($this->currentPage > 0 && $this->currentXPosition < $margin) {
            $this->currentXPosition = $margin;
        }
    }

    public function SetTopMargin($margin)
    {
        // Set top margin
        $this->topMargin = $margin;
    }

    public function SetRightMargin($margin)
    {
        // Set right margin
        $this->rightMargin = $margin;
    }

    public function SetAutoPageBreak($auto, $margin = 0)
    {
        // Set auto page break mode and triggering margin
        $this->AutoPageBreak = $auto;
        $this->pageBreakMargin = $margin;
        $this->PageBreakTrigger = $this->pageHeight - $margin;
    }

    public function SetDisplayMode($zoom, $layout = 'default')
    {
        // Set display mode in viewer
        if ($zoom == 'fullpage' || $zoom == 'fullwidth' || $zoom == 'real' || $zoom == 'default' || !is_string($zoom)) {
            $this->ZoomMode = $zoom;
        } else {
            $this->Error('Incorrect zoom display mode: ' . $zoom);
        }
        if ($layout == 'single' || $layout == 'continuous' || $layout == 'two' || $layout == 'default') {
            $this->LayoutMode = $layout;
        } else {
            $this->Error('Incorrect layout display mode: ' . $layout);
        }
    }

    public function SetCompression($compress)
    {
        // Set page compression
        if (function_exists('gzcompress')) {
            $this->compressionEnabled = $compress;
        } else {
            $this->compressionEnabled = false;
        }
    }

    public function SetTitle($title, $isUTF8 = false)
    {
        // Title of document
        $this->metadata['Title'] = $isUTF8 ? $title : $this->_UTF8encode($title);
    }

    public function SetAuthor($author, $isUTF8 = false)
    {
        // Author of document
        $this->metadata['Author'] = $isUTF8 ? $author : $this->_UTF8encode($author);
    }

    public function SetSubject($subject, $isUTF8 = false)
    {
        // Subject of document
        $this->metadata['Subject'] = $isUTF8 ? $subject : $this->_UTF8encode($subject);
    }

    public function SetKeywords($keywords, $isUTF8 = false)
    {
        // Keywords of document
        $this->metadata['Keywords'] = $isUTF8 ? $keywords : $this->_UTF8encode($keywords);
    }

    public function SetCreator($creator, $isUTF8 = false)
    {
        // Creator of document
        $this->metadata['Creator'] = $isUTF8 ? $creator : $this->_UTF8encode($creator);
    }

    public function AliasNbPages($alias = '{nb}')
    {
        // Define an alias for total number of pages
        $this->AliasNbPages = $alias;
    }

    public function Error($msg)
    {
        // Fatal error
        throw new Exception('tFPDF error: ' . $msg);
    }

    public function Close()
    {
        // Terminate document
        if ($this->currentDocumentState == 3) {
            return;
        }
        if ($this->currentPage == 0) {
            $this->AddPage();
        }
        // Page footer
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        // Close page
        $this->_endpage();
        // Close document
        $this->_enddoc();
    }

    public function AddPage($orientation = '', $size = '', $rotation = 0)
    {
        // Start a new page
        if ($this->currentDocumentState == 3) {
            $this->Error('The document is closed');
        }
        $family = $this->currentFontFamily;
        $style = $this->currentFontStyle . ($this->isUnderline ? 'U' : '');
        $fontsize = $this->currentFontSizeInPoints;
        $lw = $this->lineWidth;
        $dc = $this->drawColor;
        $fc = $this->fillColor;
        $tc = $this->textColor;
        $cf = $this->fillColorEqualsTextColor;
        if ($this->currentPage > 0) {
            // Page footer
            $this->InFooter = true;
            $this->Footer();
            $this->InFooter = false;
            // Close page
            $this->_endpage();
        }
        // Start new page
        $this->_beginpage($orientation, $size, $rotation);
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
        $this->fillColorEqualsTextColor = $cf;
        // Page header
        $this->InHeader = true;
        $this->Header();
        $this->InHeader = false;
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
        $this->fillColorEqualsTextColor = $cf;
    }

    public function Header()
    {
        // To be implemented in your own inherited class
    }

    public function Footer()
    {
        // To be implemented in your own inherited class
    }

    public function PageNo()
    {
        // Get current page number
        return $this->currentPage;
    }

    public function SetDrawColor($r, $g = null, $b = null)
    {
        // Set color for all stroking operations
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->drawColor = sprintf('%.3F G', $r / 255);
        } else {
            $this->drawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
        }
        if ($this->currentPage > 0) {
            $this->_out($this->drawColor);
        }
    }

    public function SetFillColor($r, $g = null, $b = null)
    {
        // Set color for all filling operations
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->fillColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->fillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->fillColorEqualsTextColor = ($this->fillColor != $this->textColor);
        if ($this->currentPage > 0) {
            $this->_out($this->fillColor);
        }
    }

    public function SetTextColor($r, $g = null, $b = null)
    {
        // Set color for text
        if (($r == 0 && $g == 0 && $b == 0) || $g === null) {
            $this->textColor = sprintf('%.3F g', $r / 255);
        } else {
            $this->textColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
        }
        $this->fillColorEqualsTextColor = ($this->fillColor != $this->textColor);
    }

    public function GetStringWidth($s)
    {
        // Get width of a string in the current font
        $s = (string) $s;
        $cw = $this->currentFont['cw'];
        $w = 0;
        $unicode = $this->UTF8StringToArray($s);
        foreach ($unicode as $char) {
            if (isset($cw[2 * $char])) {
                $w += (ord($cw[2 * $char]) << 8) + ord($cw[2 * $char + 1]);
            } elseif ($char > 0 && $char < 128 && isset($cw[chr($char)])) {
                $w += $cw[chr($char)];
            } elseif (isset($this->currentFont['desc']['MissingWidth'])) {
                $w += $this->currentFont['desc']['MissingWidth'];
            } elseif (isset($this->currentFont['MissingWidth'])) {
                $w += $this->currentFont['MissingWidth'];
            } else {
                $w += 500;
            }
        }

        return $w * $this->currentFontSize / 1000;
    }

    public function SetLineWidth($width)
    {
        // Set line width
        $this->lineWidth = $width;
        if ($this->currentPage > 0) {
            $this->_out(sprintf('%.2F w', $width * $this->scaleFactor));
        }
    }

    public function Line($x1, $y1, $x2, $y2)
    {
        // Draw a line
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S', $x1 * $this->scaleFactor, ($this->pageHeight - $y1) * $this->scaleFactor, $x2 * $this->scaleFactor, ($this->pageHeight - $y2) * $this->scaleFactor));
    }

    public function Rect($x, $y, $w, $h, $style = '')
    {
        // Draw a rectangle
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s', $x * $this->scaleFactor, ($this->pageHeight - $y) * $this->scaleFactor, $w * $this->scaleFactor, -$h * $this->scaleFactor, $op));
    }

    public function AddFont($family, $style = '', $file = '')
    {
        // Add a TrueType, OpenType or Type1 font
        $family = strtolower($family);
        $style = strtoupper($style);
        if ($style == 'IB') {
            $style = 'BI';
        }
        if ($file == '') {
            $file = str_replace(' ', '', $family) . strtolower($style) . '.ttf';
        }
        $fontkey = $family . $style;
        if (isset($this->usedFonts[$fontkey])) {
            return;
        }
        if (defined('_SYSTEM_TTFONTS') && file_exists(_SYSTEM_TTFONTS . $file)) {
            $ttffilename = _SYSTEM_TTFONTS . $file;
        } else {
            $ttffilename = $this->fontPath . '/' . $file;
        }
        $unifilename = $this->fontPath . 'unifont/' . strtolower(substr($file, 0, strpos($file, '.')));
        $name = '';
        $originalsize = 0;
        $ttfstat = stat($ttffilename);
        if (file_exists($unifilename . '.mtx.php')) {
            include $unifilename . '.mtx.php';
        }
        if (!isset($type) || !isset($name) || $originalsize != $ttfstat['size']) {
            $ttffile = $ttffilename;

            $ttf = new TtFontFile();
            $ttf->getMetrics($ttffile);
            $cw = $ttf->charWidths;
            $name = preg_replace('/[ ()]/', '', $ttf->fullName);

            $desc = ['Ascent' => round($ttf->ascent),
                'Descent' => round($ttf->descent),
                'CapHeight' => round($ttf->capHeight),
                'Flags' => $ttf->flags,
                'FontBBox' => '[' . round($ttf->bbox[0]) . ' ' . round($ttf->bbox[1]) . ' ' . round($ttf->bbox[2]) . ' ' . round($ttf->bbox[3]) . ']',
                'ItalicAngle' => $ttf->italicAngle,
                'StemV' => round($ttf->stemV),
                'MissingWidth' => round($ttf->defaultWidth)];
            $up = round($ttf->underlinePosition);
            $ut = round($ttf->underlineThickness);
            $originalsize = $ttfstat['size'] + 0;
            $type = 'TTF';
            // Generate metrics .php file
            $s = '<?php' . "\n";
            $s .= '$name=\'' . $name . "';\n";
            $s .= '$type=\'' . $type . "';\n";
            $s .= '$desc=' . var_export($desc, true) . ";\n";
            $s .= '$up=' . $up . ";\n";
            $s .= '$ut=' . $ut . ";\n";
            $s .= '$ttffile=\'' . $ttffile . "';\n";
            $s .= '$originalsize=' . $originalsize . ";\n";
            $s .= '$fontkey=\'' . $fontkey . "';\n";
            $s .= '?>';
            if (is_writable(dirname($this->fontPath . 'unifont/x'))) {
                $fh = fopen($unifilename . '.mtx.php', 'w');
                fwrite($fh, $s, strlen($s));
                fclose($fh);
                $fh = fopen($unifilename . '.cw.dat', 'wb');
                fwrite($fh, $cw, strlen($cw));
                fclose($fh);
                @unlink($unifilename . '.cw127.php');
            }
            unset($ttf);
        } else {
            $cw = @file_get_contents($unifilename . '.cw.dat');
        }
        $i = count($this->usedFonts) + 1;
        if (!empty($this->AliasNbPages)) {
            $sbarr = range(0, 57);
        } else {
            $sbarr = range(0, 32);
        }
        $this->usedFonts[$fontkey] = ['i' => $i, 'type' => $type, 'name' => $name, 'desc' => $desc, 'up' => $up, 'ut' => $ut, 'cw' => $cw, 'ttffile' => $ttffile, 'fontkey' => $fontkey, 'subset' => $sbarr, 'unifilename' => $unifilename];

        $this->fontFiles[$fontkey] = ['length1' => $originalsize, 'type' => 'TTF', 'ttffile' => $ttffile];
        $this->fontFiles[$file] = ['type' => 'TTF'];
        unset($cw);
    }

    public function SetFont($family, $style = '', $size = 0)
    {
        // Select a font; size given in points
        if ($family == '') {
            $family = $this->currentFontFamily;
        } else {
            $family = strtolower($family);
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
        if ($this->currentPage > 0) {
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->currentFont['i'], $this->currentFontSizeInPoints));
        }
    }

    public function SetFontSize($size)
    {
        // Set font size in points
        if ($this->currentFontSizeInPoints == $size) {
            return;
        }
        $this->currentFontSizeInPoints = $size;
        $this->currentFontSize = $size / $this->scaleFactor;
        if ($this->currentPage > 0) {
            $this->_out(sprintf('BT /F%d %.2F Tf ET', $this->currentFont['i'], $this->currentFontSizeInPoints));
        }
    }

    public function AddLink()
    {
        // Create a new internal link
        $n = count($this->links) + 1;
        $this->links[$n] = [0, 0];

        return $n;
    }

    public function SetLink($link, $y = 0, $page = -1)
    {
        // Set destination of internal link
        if ($y == -1) {
            $y = $this->currentYPosition;
        }
        if ($page == -1) {
            $page = $this->currentPage;
        }
        $this->links[$link] = [$page, $y];
    }

    public function Link($x, $y, $w, $h, $link)
    {
        // Put a link on the page
        $this->PageLinks[$this->currentPage][] = [$x * $this->scaleFactor, $this->hPt - $y * $this->scaleFactor, $w * $this->scaleFactor, $h * $this->scaleFactor, $link];
    }

    public function Text($x, $y, $txt)
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
        if ($this->fillColorEqualsTextColor) {
            $s = 'q ' . $this->textColor . ' ' . $s . ' Q';
        }
        $this->_out($s);
    }

    public function AcceptPageBreak()
    {
        // Accept automatic page break or not
        return $this->AutoPageBreak;
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        // Output a cell
        $txt = (string) $txt;
        $k = $this->scaleFactor;
        if ($this->currentYPosition + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
            // Automatic page break
            $x = $this->currentXPosition;
            $ws = $this->wordSpacing;
            if ($ws > 0) {
                $this->wordSpacing = 0;
                $this->_out('0 Tw');
            }
            $this->AddPage($this->currentOrientation, $this->currentPageSize, $this->currentPageOrientation);
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
                $dx = $w - $this->cellMargin - $this->GetStringWidth($txt);
            } elseif ($align == 'C') {
                $dx = ($w - $this->GetStringWidth($txt)) / 2;
            } else {
                $dx = $this->cellMargin;
            }
            if ($this->fillColorEqualsTextColor) {
                $s .= 'q ' . $this->textColor . ' ';
            }
            // If multibyte, Tw has no effect - do word spacing using an adjustment before each space
            if ($this->wordSpacing) {
                foreach ($this->UTF8StringToArray($txt) as $uni) {
                    $this->currentFont['subset'][$uni] = $uni;
                }
                $space = $this->_escape($this->UTF8ToUTF16BE(' ', false));
                $s .= sprintf('BT 0 Tw %.2F %.2F Td [', ($this->currentXPosition + $dx) * $k, ($this->pageHeight - ($this->currentYPosition + .5 * $h + .3 * $this->currentFontSize)) * $k);
                $t = explode(' ', $txt);
                $numt = count($t);
                for ($i = 0; $i < $numt; ++$i) {
                    $tx = $t[$i];
                    $tx = '(' . $this->_escape($this->UTF8ToUTF16BE($tx, false)) . ')';
                    $s .= sprintf('%s ', $tx);
                    if (($i + 1) < $numt) {
                        $adj = -($this->wordSpacing * $this->scaleFactor) * 1000 / $this->currentFontSizeInPoints;
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
                $s .= sprintf('BT %.2F %.2F Td %s Tj ET', ($this->currentXPosition + $dx) * $k, ($this->pageHeight - ($this->currentYPosition + .5 * $h + .3 * $this->currentFontSize)) * $k, $txt2);
            }
            if ($this->isUnderline) {
                $s .= ' ' . $this->_dounderline($this->currentXPosition + $dx, $this->currentYPosition + .5 * $h + .3 * $this->currentFontSize, $txt);
            }
            if ($this->fillColorEqualsTextColor) {
                $s .= ' Q';
            }
            if ($link) {
                $this->Link($this->currentXPosition + $dx, $this->currentYPosition + .5 * $h - .5 * $this->currentFontSize, $this->GetStringWidth($txt), $this->currentFontSize, $link);
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

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        // Output text with automatic or explicit line breaks
        if (!isset($this->currentFont)) {
            $this->Error('No font has been set');
        }
        $cw = $this->currentFont['cw'];
        if ($w == 0) {
            $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
        }
        $wmax = ($w - 2 * $this->cellMargin);
        // $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = mb_strlen($s, 'utf-8');
        while ($nb > 0 && mb_substr($s, $nb - 1, 1, 'utf-8') == "\n") {
            --$nb;
        }
        $b = 0;
        if ($border) {
            if ($border == 1) {
                $border = 'LTRB';
                $b = 'LRT';
                $b2 = 'LR';
            } else {
                $b2 = '';
                if (strpos($border, 'L') !== false) {
                    $b2 .= 'L';
                }
                if (strpos($border, 'R') !== false) {
                    $b2 .= 'R';
                }
                $b = (strpos($border, 'T') !== false) ? $b2 . 'T' : $b2;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
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

            $l += $this->GetStringWidth($c);

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
        if ($border && strpos($border, 'B') !== false) {
            $b .= 'B';
        }
        $this->Cell($w, $h, mb_substr($s, $j, $i - $j, 'UTF-8'), $b, 2, $align, $fill);
        $this->currentXPosition = $this->leftMargin;
    }

    public function Write($h, $txt, $link = '')
    {
        // Output text in flowing mode
        if (!isset($this->currentFont)) {
            $this->Error('No font has been set');
        }
        $cw = $this->currentFont['cw'];
        $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
        $wmax = ($w - 2 * $this->cellMargin);
        $s = str_replace("\r", '', (string) $txt);
        $nb = mb_strlen($s, 'UTF-8');
        if ($nb == 1 && $s == ' ') {
            $this->currentXPosition += $this->GetStringWidth($s);

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
                    $wmax = ($w - 2 * $this->cellMargin);
                }
                ++$nl;

                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }

            $l += $this->GetStringWidth($c);

            if ($l > $wmax) {
                // Automatic line break
                if ($sep == -1) {
                    if ($this->currentXPosition > $this->leftMargin) {
                        // Move to next line
                        $this->currentXPosition = $this->leftMargin;
                        $this->currentYPosition += $h;
                        $w = $this->pageWidth - $this->rightMargin - $this->currentXPosition;
                        $wmax = ($w - 2 * $this->cellMargin);
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
                    $wmax = ($w - 2 * $this->cellMargin);
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

    public function Ln($h = null)
    {
        // Line feed; default value is the last cell height
        $this->currentXPosition = $this->leftMargin;
        if ($h === null) {
            $this->currentYPosition += $this->lastPrintedCellHeight;
        } else {
            $this->currentYPosition += $h;
        }
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '')
    {
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
            if ($type == 'jpeg') {
                $type = 'jpg';
            }
            $mtd = '_parse' . $type;
            if (!method_exists($this, $mtd)) {
                $this->Error('Unsupported image type: ' . $type);
            }
            $info = $this->{$mtd}($file);
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
            if ($this->currentYPosition + $h > $this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
                // Automatic page break
                $x2 = $this->currentXPosition;
                $this->AddPage($this->currentOrientation, $this->currentPageSize, $this->currentPageOrientation);
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

    public function GetPageWidth()
    {
        // Get current page width
        return $this->pageWidth;
    }

    public function GetPageHeight()
    {
        // Get current page height
        return $this->pageHeight;
    }

    public function GetX()
    {
        // Get x position
        return $this->currentXPosition;
    }

    public function SetX($x)
    {
        // Set x position
        if ($x >= 0) {
            $this->currentXPosition = $x;
        } else {
            $this->currentXPosition = $this->pageWidth + $x;
        }
    }

    public function GetY()
    {
        // Get y position
        return $this->currentYPosition;
    }

    public function SetY($y, $resetX = true)
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

    public function SetXY($x, $y)
    {
        // Set x and y positions
        $this->SetX($x);
        $this->SetY($y, false);
    }

    public function Output($dest = '', $name = '', $isUTF8 = false)
    {
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

    public function setFontPath(string $fontPath): void
    {
        $this->fontPath = $fontPath;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    // Protected methods

    protected function _dochecks()
    {
        // Check availability of mbstring
        if (!function_exists('mb_strlen')) {
            $this->Error('mbstring extension is not available');
        }
    }

    protected function _checkoutput()
    {
        if (PHP_SAPI != 'cli') {
            if (headers_sent($file, $line)) {
                $this->Error("Some data has already been output, can't send PDF file (output started at {$file}:{$line})");
            }
        }
        if (ob_get_length()) {
            // The output buffer is not empty
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', ob_get_contents())) {
                // It contains only a UTF-8 BOM and/or whitespace, let's clean it
                ob_clean();
            } else {
                $this->Error("Some data has already been output, can't send PDF file");
            }
        }
    }

    protected function _getpagesize($size)
    {
        if (is_string($size)) {
            $size = strtolower($size);
            if (!isset($this->StdPageSizes[$size])) {
                $this->Error('Unknown page size: ' . $size);
            }
            $a = $this->StdPageSizes[$size];

            return [$a[0] / $this->scaleFactor, $a[1] / $this->scaleFactor];
        }

        if ($size[0] > $size[1]) {
            return [$size[1], $size[0]];
        }

        return $size;
    }

    protected function _beginpage($orientation, $size, $rotation)
    {
        ++$this->currentPage;
        $this->pages[$this->currentPage] = '';
        $this->PageLinks[$this->currentPage] = [];
        $this->currentDocumentState = 2;
        $this->currentXPosition = $this->leftMargin;
        $this->currentYPosition = $this->topMargin;
        $this->currentFontFamily = '';
        // Check page size and orientation
        if ($orientation == '') {
            $orientation = $this->defaultOrientation;
        } else {
            $orientation = strtoupper($orientation[0]);
        }
        if ($size == '') {
            $size = $this->defaultPageSize;
        } else {
            $size = $this->_getpagesize($size);
        }
        if ($orientation != $this->currentOrientation || $size[0] != $this->currentPageSize[0] || $size[1] != $this->currentPageSize[1]) {
            // New size or orientation
            if ($orientation == 'P') {
                $this->pageWidth = $size[0];
                $this->pageHeight = $size[1];
            } else {
                $this->pageWidth = $size[1];
                $this->pageHeight = $size[0];
            }
            $this->pageWidthInPoints = $this->pageWidth * $this->scaleFactor;
            $this->hPt = $this->pageHeight * $this->scaleFactor;
            $this->PageBreakTrigger = $this->pageHeight - $this->pageBreakMargin;
            $this->currentOrientation = $orientation;
            $this->currentPageSize = $size;
        }
        if ($orientation != $this->defaultOrientation || $size[0] != $this->defaultPageSize[0] || $size[1] != $this->defaultPageSize[1]) {
            $this->pageInfo[$this->currentPage]['size'] = [$this->pageWidthInPoints, $this->hPt];
        }
        if ($rotation != 0) {
            if ($rotation % 90 != 0) {
                $this->Error('Incorrect rotation value: ' . $rotation);
            }
            $this->pageInfo[$this->currentPage]['rotation'] = $rotation;
        }
        $this->currentPageOrientation = $rotation;
    }

    protected function _endpage()
    {
        $this->currentDocumentState = 1;
    }

    protected function _loadfont($font)
    {
        // Load a font definition file from the font directory
        if (strpos($font, '/') !== false || strpos($font, '\\') !== false) {
            $this->Error('Incorrect font definition file name: ' . $font);
        }

        include $this->fontPath . $font;
        if (!isset($name)) {
            $this->Error('Could not include font definition file');
        }
        if (isset($enc)) {
            $enc = strtolower($enc);
        }
        if (!isset($subsetted)) {
            $subsetted = false;
        }

        return get_defined_vars();
    }

    protected function _isascii($s)
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

    protected function _httpencode($param, $value, $isUTF8)
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

    protected function _UTF8encode($s)
    {
        // Convert ISO-8859-1 to UTF-8
        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    protected function _UTF8toUTF16($s)
    {
        // Convert UTF-8 to UTF-16BE with BOM
        return "\xFE\xFF" . mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    }

    protected function _escape($s)
    {
        // Escape special characters
        if (strpos($s, '(') !== false || strpos($s, ')') !== false || strpos($s, '\\') !== false || strpos($s, "\r") !== false) {
            return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
        }

        return $s;
    }

    protected function _textstring($s)
    {
        // Format a text string
        if (!$this->_isascii($s)) {
            $s = $this->_UTF8toUTF16($s);
        }

        return '(' . $this->_escape($s) . ')';
    }

    protected function _dounderline($x, $y, $txt)
    {
        // Underline text
        $up = $this->currentFont['up'];
        $ut = $this->currentFont['ut'];
        $w = $this->GetStringWidth($txt) + $this->wordSpacing * substr_count($txt, ' ');

        return sprintf('%.2F %.2F %.2F %.2F re f', $x * $this->scaleFactor, ($this->pageHeight - ($y - $up / 1000 * $this->currentFontSize)) * $this->scaleFactor, $w * $this->scaleFactor, -$ut / 1000 * $this->currentFontSizeInPoints);
    }

    protected function _parsejpg($file)
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

    protected function _parsepng($file)
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

    protected function _parsepngstream($f, $file)
    {
        // Check signature
        if ($this->_readstream($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) {
            $this->Error('Not a PNG file: ' . $file);
        }

        // Read header chunk
        $this->_readstream($f, 4);
        if ($this->_readstream($f, 4) != 'IHDR') {
            $this->Error('Incorrect PNG file: ' . $file);
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
            $this->Error('Unknown color type: ' . $file);
        }
        if (ord($this->_readstream($f, 1)) != 0) {
            $this->Error('Unknown compression method: ' . $file);
        }
        if (ord($this->_readstream($f, 1)) != 0) {
            $this->Error('Unknown filter method: ' . $file);
        }
        if (ord($this->_readstream($f, 1)) != 0) {
            $this->Error('Interlacing not supported: ' . $file);
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

        if ($colspace == 'Indexed' && empty($pal)) {
            $this->Error('Missing palette in ' . $file);
        }
        $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'dp' => $dp, 'pal' => $pal, 'trns' => $trns];
        if ($ct >= 4) {
            // Extract alpha channel
            if (!function_exists('gzuncompress')) {
                $this->Error('Zlib not available, can\'t handle alpha channel: ' . $file);
            }
            $data = gzuncompress($data);
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
            if ($this->PDFVersion < '1.4') {
                $this->PDFVersion = '1.4';
            }
        }
        $info['data'] = $data;

        return $info;
    }

    protected function _readstream($f, $n)
    {
        // Read n bytes from stream
        $res = '';
        while ($n > 0 && !feof($f)) {
            $s = fread($f, $n);
            if ($s === false) {
                $this->Error('Error while reading stream');
            }
            $n -= strlen($s);
            $res .= $s;
        }
        if ($n > 0) {
            $this->Error('Unexpected end of stream');
        }

        return $res;
    }

    protected function _readint($f)
    {
        // Read a 4-byte integer from stream
        $a = unpack('Ni', $this->_readstream($f, 4));

        return $a['i'];
    }

    protected function _parsegif($file)
    {
        // Extract info from a GIF file (via PNG conversion)
        if (!function_exists('imagepng')) {
            $this->Error('GD extension is required for GIF support');
        }
        if (!function_exists('imagecreatefromgif')) {
            $this->Error('GD has no GIF read support');
        }
        $im = imagecreatefromgif($file);
        if (!$im) {
            $this->Error('Missing or incorrect image file: ' . $file);
        }
        imageinterlace($im, 0);
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);
        $f = fopen('php://temp', 'rb+');
        if (!$f) {
            $this->Error('Unable to create memory stream');
        }
        fwrite($f, $data);
        rewind($f);
        $info = $this->_parsepngstream($f, $file);
        fclose($f);

        return $info;
    }

    protected function _out($s)
    {
        // Add a line to the document
        if ($this->currentDocumentState == 2) {
            $this->pages[$this->currentPage] .= $s . "\n";
        } elseif ($this->currentDocumentState == 1) {
            $this->_put($s);
        } elseif ($this->currentDocumentState == 0) {
            $this->Error('No page has been added yet');
        } elseif ($this->currentDocumentState == 3) {
            $this->Error('The document is closed');
        }
    }

    protected function _put($s)
    {
        $this->pdfFileBuffer .= $s . "\n";
    }

    protected function _getoffset()
    {
        return strlen($this->pdfFileBuffer);
    }

    protected function _newobj($n = null)
    {
        // Begin a new object
        if ($n === null) {
            $n = ++$this->currentObjectNumber;
        }
        $this->objectOffsets[$n] = $this->_getoffset();
        $this->_put($n . ' 0 obj');
    }

    protected function _putstream($data)
    {
        $this->_put('stream');
        $this->_put($data);
        $this->_put('endstream');
    }

    protected function _putstreamobject($data)
    {
        if ($this->compressionEnabled) {
            $entries = '/Filter /FlateDecode ';
            $data = gzcompress($data);
        } else {
            $entries = '';
        }
        $entries .= '/Length ' . strlen($data);
        $this->_newobj();
        $this->_put('<<' . $entries . '>>');
        $this->_putstream($data);
        $this->_put('endobj');
    }

    protected function _putlinks($n)
    {
        foreach ($this->PageLinks[$n] as $pl) {
            $this->_newobj();
            $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
            $s = '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
            if (is_string($pl[4])) {
                $s .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';
            } else {
                $l = $this->links[$pl[4]];
                if (isset($this->pageInfo[$l[0]]['size'])) {
                    $h = $this->pageInfo[$l[0]]['size'][1];
                } else {
                    $h = ($this->defaultOrientation == 'P') ? $this->defaultPageSize[1] * $this->scaleFactor : $this->defaultPageSize[0] * $this->scaleFactor;
                }
                $s .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', $this->pageInfo[$l[0]]['n'], $h - $l[1] * $this->scaleFactor);
            }
            $this->_put($s);
            $this->_put('endobj');
        }
    }

    protected function _putpage($n)
    {
        $this->_newobj();
        $this->_put('<</Type /Page');
        $this->_put('/Parent 1 0 R');
        if (isset($this->pageInfo[$n]['size'])) {
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->pageInfo[$n]['size'][0], $this->pageInfo[$n]['size'][1]));
        }
        if (isset($this->pageInfo[$n]['rotation'])) {
            $this->_put('/Rotate ' . $this->pageInfo[$n]['rotation']);
        }
        $this->_put('/Resources 2 0 R');
        if (!empty($this->PageLinks[$n])) {
            $s = '/Annots [';
            foreach ($this->PageLinks[$n] as $pl) {
                $s .= $pl[5] . ' 0 R ';
            }
            $s .= ']';
            $this->_put($s);
        }
        if ($this->transparencyEnabled) {
            $this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
        }
        $this->_put('/Contents ' . ($this->currentObjectNumber + 1) . ' 0 R>>');
        $this->_put('endobj');
        // Page content
        if (!empty($this->AliasNbPages)) {
            $alias = $this->UTF8ToUTF16BE($this->AliasNbPages, false);
            $r = $this->UTF8ToUTF16BE($this->currentPage, false);
            $this->pages[$n] = str_replace($alias, $r, $this->pages[$n]);
            // Now repeat for no pages in non-subset fonts
            $this->pages[$n] = str_replace($this->AliasNbPages, $this->currentPage, $this->pages[$n]);
        }
        $this->_putstreamobject($this->pages[$n]);
        // Link annotations
        $this->_putlinks($n);
    }

    protected function _putpages()
    {
        $nb = $this->currentPage;
        $n = $this->currentObjectNumber;
        for ($i = 1; $i <= $nb; ++$i) {
            $this->pageInfo[$i]['n'] = ++$n;
            ++$n;
            foreach ($this->PageLinks[$i] as &$pl) {
                $pl[5] = ++$n;
            }
            unset($pl);
        }
        for ($i = 1; $i <= $nb; ++$i) {
            $this->_putpage($i);
        }
        // Pages root
        $this->_newobj(1);
        $this->_put('<</Type /Pages');
        $kids = '/Kids [';
        for ($i = 1; $i <= $nb; ++$i) {
            $kids .= $this->pageInfo[$i]['n'] . ' 0 R ';
        }
        $kids .= ']';
        $this->_put($kids);
        $this->_put('/Count ' . $nb);
        if ($this->defaultOrientation == 'P') {
            $w = $this->defaultPageSize[0];
            $h = $this->defaultPageSize[1];
        } else {
            $w = $this->defaultPageSize[1];
            $h = $this->defaultPageSize[0];
        }
        $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $w * $this->scaleFactor, $h * $this->scaleFactor));
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putfonts()
    {
        foreach ($this->fontFiles as $file => $info) {
            if (!isset($info['type']) || $info['type'] != 'TTF') {
                // Font file embedding
                $this->_newobj();
                $this->fontFiles[$file]['n'] = $this->currentObjectNumber;
                $font = file_get_contents($this->fontPath . $file, true);
                if (!$font) {
                    $this->Error('Font file not found: ' . $file);
                }
                $compressed = (substr($file, -2) == '.z');
                if (!$compressed && isset($info['length2'])) {
                    $font = substr($font, 6, $info['length1']) . substr($font, 6 + $info['length1'] + 6, $info['length2']);
                }
                $this->_put('<</Length ' . strlen($font));
                if ($compressed) {
                    $this->_put('/Filter /FlateDecode');
                }
                $this->_put('/Length1 ' . $info['length1']);
                if (isset($info['length2'])) {
                    $this->_put('/Length2 ' . $info['length2'] . ' /Length3 0');
                }
                $this->_put('>>');
                $this->_putstream($font);
                $this->_put('endobj');
            }
        }
        foreach ($this->usedFonts as $k => $font) {
            // Encoding
            if (isset($font['diff'])) {
                if (!isset($this->encodings[$font['enc']])) {
                    $this->_newobj();
                    $this->_put('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $font['diff'] . ']>>');
                    $this->_put('endobj');
                    $this->encodings[$font['enc']] = $this->currentObjectNumber;
                }
            }
            // ToUnicode CMap
            if (isset($font['uv'])) {
                if (isset($font['enc'])) {
                    $cmapkey = $font['enc'];
                } else {
                    $cmapkey = $font['name'];
                }
                if (!isset($this->cmaps[$cmapkey])) {
                    $cmap = $this->_tounicodecmap($font['uv']);
                    $this->_putstreamobject($cmap);
                    $this->cmaps[$cmapkey] = $this->currentObjectNumber;
                }
            }
            // Font object
            $type = $font['type'];
            $name = $font['name'];
            if ($type == 'Core') {
                // Core font
                $this->usedFonts[$k]['n'] = $this->currentObjectNumber + 1;
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /' . $name);
                $this->_put('/Subtype /Type1');
                if ($name != 'Symbol' && $name != 'ZapfDingbats') {
                    $this->_put('/Encoding /WinAnsiEncoding');
                }
                if (isset($font['uv'])) {
                    $this->_put('/ToUnicode ' . $this->cmaps[$cmapkey] . ' 0 R');
                }
                $this->_put('>>');
                $this->_put('endobj');
            } elseif ($type == 'Type1' || $type == 'TrueType') {
                // Additional Type1 or TrueType/OpenType font
                if (isset($font['subsetted']) && $font['subsetted']) {
                    $name = 'AAAAAA+' . $name;
                }
                $this->usedFonts[$k]['n'] = $this->currentObjectNumber + 1;
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/BaseFont /' . $name);
                $this->_put('/Subtype /' . $type);
                $this->_put('/FirstChar 32 /LastChar 255');
                $this->_put('/Widths ' . ($this->currentObjectNumber + 1) . ' 0 R');
                $this->_put('/FontDescriptor ' . ($this->currentObjectNumber + 2) . ' 0 R');

                if ($font['enc']) {
                    if (isset($font['diff'])) {
                        $this->_put('/Encoding ' . $this->encodings[$font['enc']] . ' 0 R');
                    } else {
                        $this->_put('/Encoding /WinAnsiEncoding');
                    }
                }

                if (isset($font['uv'])) {
                    $this->_put('/ToUnicode ' . $this->cmaps[$cmapkey] . ' 0 R');
                }
                $this->_put('>>');
                $this->_put('endobj');
                // Widths
                $this->_newobj();
                $cw = $font['cw'];
                $s = '[';
                for ($i = 32; $i <= 255; ++$i) {
                    $s .= $cw[chr($i)] . ' ';
                }
                $this->_put($s . ']');
                $this->_put('endobj');
                // Descriptor
                $this->_newobj();
                $s = '<</Type /FontDescriptor /FontName /' . $name;
                foreach ($font['desc'] as $k => $v) {
                    $s .= ' /' . $k . ' ' . $v;
                }

                if (!empty($font['file'])) {
                    $s .= ' /FontFile' . ($type == 'Type1' ? '' : '2') . ' ' . $this->fontFiles[$font['file']]['n'] . ' 0 R';
                }
                $this->_put($s . '>>');
                $this->_put('endobj');
            }
            // TrueType embedded SUBSETS or FULL
            elseif ($type == 'TTF') {
                $this->usedFonts[$k]['n'] = $this->currentObjectNumber + 1;

                $ttf = new TTFontFile();
                $fontname = 'MPDFAA+' . $font['name'];
                $subset = $font['subset'];
                unset($subset[0]);
                $ttfontstream = $ttf->makeSubset($font['ttffile'], $subset);
                $ttfontsize = strlen($ttfontstream);
                $fontstream = gzcompress($ttfontstream);
                $codeToGlyph = $ttf->codeToGlyph;
                unset($codeToGlyph[0]);

                // Type0 Font
                // A composite font - a font composed of other fonts, organized hierarchically
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/Subtype /Type0');
                $this->_put('/BaseFont /' . $fontname . '');
                $this->_put('/Encoding /Identity-H');
                $this->_put('/DescendantFonts [' . ($this->currentObjectNumber + 1) . ' 0 R]');
                $this->_put('/ToUnicode ' . ($this->currentObjectNumber + 2) . ' 0 R');
                $this->_put('>>');
                $this->_put('endobj');

                // CIDFontType2
                // A CIDFont whose glyph descriptions are based on TrueType font technology
                $this->_newobj();
                $this->_put('<</Type /Font');
                $this->_put('/Subtype /CIDFontType2');
                $this->_put('/BaseFont /' . $fontname . '');
                $this->_put('/CIDSystemInfo ' . ($this->currentObjectNumber + 2) . ' 0 R');
                $this->_put('/FontDescriptor ' . ($this->currentObjectNumber + 3) . ' 0 R');
                if (isset($font['desc']['MissingWidth'])) {
                    $this->_out('/DW ' . $font['desc']['MissingWidth'] . '');
                }

                $this->_putTTfontwidths($font, $ttf->maxUni);

                $this->_put('/CIDToGIDMap ' . ($this->currentObjectNumber + 4) . ' 0 R');
                $this->_put('>>');
                $this->_put('endobj');

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
                $this->_put('<</Length ' . strlen($toUni) . '>>');
                $this->_putstream($toUni);
                $this->_put('endobj');

                // CIDSystemInfo dictionary
                $this->_newobj();
                $this->_put('<</Registry (Adobe)');
                $this->_put('/Ordering (UCS)');
                $this->_put('/Supplement 0');
                $this->_put('>>');
                $this->_put('endobj');

                // Font descriptor
                $this->_newobj();
                $this->_put('<</Type /FontDescriptor');
                $this->_put('/FontName /' . $fontname);
                foreach ($font['desc'] as $kd => $v) {
                    if ($kd == 'Flags') {
                        $v = $v | 4;
                        $v = $v & ~32;
                    }	// SYMBOLIC font flag
                    $this->_out(' /' . $kd . ' ' . $v);
                }
                $this->_put('/FontFile2 ' . ($this->currentObjectNumber + 2) . ' 0 R');
                $this->_put('>>');
                $this->_put('endobj');

                // Embed CIDToGIDMap
                // A specification of the mapping from CIDs to glyph indices
                $cidtogidmap = '';
                $cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
                foreach ($codeToGlyph as $cc => $glyph) {
                    $cidtogidmap[$cc * 2] = chr($glyph >> 8);
                    $cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xFF);
                }
                $cidtogidmap = gzcompress($cidtogidmap);
                $this->_newobj();
                $this->_put('<</Length ' . strlen($cidtogidmap) . '');
                $this->_put('/Filter /FlateDecode');
                $this->_put('>>');
                $this->_putstream($cidtogidmap);
                $this->_put('endobj');

                // Font file
                $this->_newobj();
                $this->_put('<</Length ' . strlen($fontstream));
                $this->_put('/Filter /FlateDecode');
                $this->_put('/Length1 ' . $ttfontsize);
                $this->_put('>>');
                $this->_putstream($fontstream);
                $this->_put('endobj');
                unset($ttf);
            } else {
                // Allow for additional types
                $this->usedFonts[$k]['n'] = $this->currentObjectNumber + 1;
                $mtd = '_put' . strtolower($type);
                if (!method_exists($this, $mtd)) {
                    $this->Error('Unsupported font type: ' . $type);
                }
                $this->{$mtd}($font);
            }
        }
    }

    protected function _putTTfontwidths($font, $maxUni)
    {
        if (file_exists($font['unifilename'] . '.cw127.php')) {
            include $font['unifilename'] . '.cw127.php';
            $startcid = 128;
        } else {
            $rangeid = 0;
            $range = [];
            $prevcid = -2;
            $prevwidth = -1;
            $interval = false;
            $startcid = 1;
        }
        $cwlen = $maxUni + 1;

        // for each character
        for ($cid = $startcid; $cid < $cwlen; ++$cid) {
            if ($cid == 128 && (!file_exists($font['unifilename'] . '.cw127.php'))) {
                if (is_writable(dirname($this->fontPath . 'unifont/x'))) {
                    $fh = fopen($font['unifilename'] . '.cw127.php', 'wb');
                    $cw127 = '<?php' . "\n";
                    $cw127 .= '$rangeid=' . $rangeid . ";\n";
                    $cw127 .= '$prevcid=' . $prevcid . ";\n";
                    $cw127 .= '$prevwidth=' . $prevwidth . ";\n";
                    if ($interval) {
                        $cw127 .= '$interval=true' . ";\n";
                    } else {
                        $cw127 .= '$interval=false' . ";\n";
                    }
                    $cw127 .= '$range=' . var_export($range, true) . ";\n";
                    $cw127 .= '?>';
                    fwrite($fh, $cw127, strlen($cw127));
                    fclose($fh);
                }
            }
            if ((!isset($font['cw'][$cid * 2]) || !isset($font['cw'][$cid * 2 + 1]))
                        || ($font['cw'][$cid * 2] == "\00" && $font['cw'][$cid * 2 + 1] == "\00")) {
                continue;
            }

            $width = (ord($font['cw'][$cid * 2]) << 8) + ord($font['cw'][$cid * 2 + 1]);
            if ($width == 65535) {
                $width = 0;
            }
            if ($cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
                continue;
            }
            if (!isset($font['dw']) || (isset($font['dw']) && $width != $font['dw'])) {
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
            $nextk = $k + $cws;
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
                $w .= ' ' . $k . ' ' . ($k + count($ws) - 1) . ' ' . $ws[0];
            } else {
                $w .= ' ' . $k . ' [ ' . implode(' ', $ws) . ' ]' . "\n";
            }
        }
        $this->_out('/W [' . $w . ' ]');
    }

    protected function _tounicodecmap($uv)
    {
        $ranges = '';
        $nbr = 0;
        $chars = '';
        $nbc = 0;
        foreach ($uv as $c => $v) {
            if (is_array($v)) {
                $ranges .= sprintf("<%02X> <%02X> <%04X>\n", $c, $c + $v[1] - 1, $v[0]);
                ++$nbr;
            } else {
                $chars .= sprintf("<%02X> <%04X>\n", $c, $v);
                ++$nbc;
            }
        }
        $s = "/CIDInit /ProcSet findresource begin\n";
        $s .= "12 dict begin\n";
        $s .= "begincmap\n";
        $s .= "/CIDSystemInfo\n";
        $s .= "<</Registry (Adobe)\n";
        $s .= "/Ordering (UCS)\n";
        $s .= "/Supplement 0\n";
        $s .= ">> def\n";
        $s .= "/CMapName /Adobe-Identity-UCS def\n";
        $s .= "/CMapType 2 def\n";
        $s .= "1 begincodespacerange\n";
        $s .= "<00> <FF>\n";
        $s .= "endcodespacerange\n";
        if ($nbr > 0) {
            $s .= "{$nbr} beginbfrange\n";
            $s .= $ranges;
            $s .= "endbfrange\n";
        }
        if ($nbc > 0) {
            $s .= "{$nbc} beginbfchar\n";
            $s .= $chars;
            $s .= "endbfchar\n";
        }
        $s .= "endcmap\n";
        $s .= "CMapName currentdict /CMap defineresource pop\n";
        $s .= "end\n";
        $s .= 'end';

        return $s;
    }

    protected function _putimages()
    {
        foreach (array_keys($this->usedImages) as $file) {
            $this->_putimage($this->usedImages[$file]);
            unset($this->usedImages[$file]['data'], $this->usedImages[$file]['smask']);
        }
    }

    protected function _putimage(&$info)
    {
        $this->_newobj();
        $info['n'] = $this->currentObjectNumber;
        $this->_put('<</Type /XObject');
        $this->_put('/Subtype /Image');
        $this->_put('/Width ' . $info['w']);
        $this->_put('/Height ' . $info['h']);
        if ($info['cs'] == 'Indexed') {
            $this->_put('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->currentObjectNumber + 1) . ' 0 R]');
        } else {
            $this->_put('/ColorSpace /' . $info['cs']);
            if ($info['cs'] == 'DeviceCMYK') {
                $this->_put('/Decode [1 0 1 0 1 0 1 0]');
            }
        }
        $this->_put('/BitsPerComponent ' . $info['bpc']);
        if (isset($info['f'])) {
            $this->_put('/Filter /' . $info['f']);
        }
        if (isset($info['dp'])) {
            $this->_put('/DecodeParms <<' . $info['dp'] . '>>');
        }
        if (isset($info['trns']) && is_array($info['trns'])) {
            $trns = '';
            for ($i = 0; $i < count($info['trns']); ++$i) {
                $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
            }
            $this->_put('/Mask [' . $trns . ']');
        }
        if (isset($info['smask'])) {
            $this->_put('/SMask ' . ($this->currentObjectNumber + 1) . ' 0 R');
        }
        $this->_put('/Length ' . strlen($info['data']) . '>>');
        $this->_putstream($info['data']);
        $this->_put('endobj');
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

    protected function _putxobjectdict()
    {
        foreach ($this->usedImages as $image) {
            $this->_put('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
        }
    }

    protected function _putresourcedict()
    {
        $this->_put('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->_put('/Font <<');
        foreach ($this->usedFonts as $font) {
            $this->_put('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
        }
        $this->_put('>>');
        $this->_put('/XObject <<');
        $this->_putxobjectdict();
        $this->_put('>>');
    }

    protected function _putresources()
    {
        $this->_putfonts();
        $this->_putimages();
        // Resource dictionary
        $this->_newobj(2);
        $this->_put('<<');
        $this->_putresourcedict();
        $this->_put('>>');
        $this->_put('endobj');
    }

    protected function _putinfo()
    {
        if ($this->createdAt === null) {
            throw new CreatedAtIsNotSetException('You must call setCreatedAt() first.');
        }
        $date = $this->createdAt->format('YmdHisO');
        $this->metadata['CreationDate'] = 'D:' . substr($date, 0, -2) . "'" . substr($date, -2) . "'";
        foreach ($this->metadata as $key => $value) {
            $this->_put('/' . $key . ' ' . $this->_textstring($value));
        }
    }

    protected function _putcatalog()
    {
        $n = $this->pageInfo[1]['n'];
        $this->_put('/Type /Catalog');
        $this->_put('/Pages 1 0 R');
        if ($this->ZoomMode == 'fullpage') {
            $this->_put('/OpenAction [' . $n . ' 0 R /Fit]');
        } elseif ($this->ZoomMode == 'fullwidth') {
            $this->_put('/OpenAction [' . $n . ' 0 R /FitH null]');
        } elseif ($this->ZoomMode == 'real') {
            $this->_put('/OpenAction [' . $n . ' 0 R /XYZ null null 1]');
        } elseif (!is_string($this->ZoomMode)) {
            $this->_put('/OpenAction [' . $n . ' 0 R /XYZ null null ' . sprintf('%.2F', $this->ZoomMode / 100) . ']');
        }
        if ($this->LayoutMode == 'single') {
            $this->_put('/PageLayout /SinglePage');
        } elseif ($this->LayoutMode == 'continuous') {
            $this->_put('/PageLayout /OneColumn');
        } elseif ($this->LayoutMode == 'two') {
            $this->_put('/PageLayout /TwoColumnLeft');
        }
    }

    protected function _putheader()
    {
        $this->_put('%PDF-' . $this->PDFVersion);
    }

    protected function _puttrailer()
    {
        $this->_put('/Size ' . ($this->currentObjectNumber + 1));
        $this->_put('/Root ' . $this->currentObjectNumber . ' 0 R');
        $this->_put('/Info ' . ($this->currentObjectNumber - 1) . ' 0 R');
    }

    protected function _enddoc()
    {
        $this->_putheader();
        $this->_putpages();
        $this->_putresources();
        // Info
        $this->_newobj();
        $this->_put('<<');
        $this->_putinfo();
        $this->_put('>>');
        $this->_put('endobj');
        // Catalog
        $this->_newobj();
        $this->_put('<<');
        $this->_putcatalog();
        $this->_put('>>');
        $this->_put('endobj');
        // Cross-ref
        $offset = $this->_getoffset();
        $this->_put('xref');
        $this->_put('0 ' . ($this->currentObjectNumber + 1));
        $this->_put('0000000000 65535 f ');
        for ($i = 1; $i <= $this->currentObjectNumber; ++$i) {
            $this->_put(sprintf('%010d 00000 n ', $this->objectOffsets[$i]));
        }
        // Trailer
        $this->_put('trailer');
        $this->_put('<<');
        $this->_puttrailer();
        $this->_put('>>');
        $this->_put('startxref');
        $this->_put($offset);
        $this->_put('%%EOF');
        $this->currentDocumentState = 3;
    }

    // ********* NEW FUNCTIONS *********
    // Converts UTF-8 strings to UTF16-BE.
    protected function UTF8ToUTF16BE($str, $setbom = true)
    {
        $outstr = '';
        if ($setbom) {
            $outstr .= "\xFE\xFF"; // Byte Order Mark (BOM)
        }
        $outstr .= mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');

        return $outstr;
    }

    // Converts UTF-8 strings to codepoints array
    protected function UTF8StringToArray($str)
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
}
