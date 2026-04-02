# stanko/pdf

> **Work in progress.** The API is actively being reworked — existing methods are
still being migrated to the immutable fluent style and signatures may change
between versions.

A modern, immutable PHP 8.1+ PDF generation library — a complete rewrite of [tFPDF](http://fpdf.org/fr/script/script92.php) (itself a fork of [FPDF](http://www.fpdf.org/)).

## Installation

```bash
composer require stanko/pdf
```

Requires PHP 8.1+ and the `mbstring` extension.

## What's different from FPDF/tFPDF

The original FPDF library used a mutable object where every method call changed internal state. This library replaces that with an **immutable fluent builder**: every method returns a new `Pdf` instance, leaving the original unchanged. Configuration is separated from drawing — dimensions, colors, and fonts are set via builder methods before the draw call, rather than passed as a long argument list.

---

### Creating a document

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->Output(
    'F',
    '/path/to/output.pdf',
);
```

</td>
<td>

```php
use Stanko\Pdf\Pdf;
use Stanko\Pdf\PageOrientation;
use Stanko\Pdf\PageSize;
use Stanko\Pdf\Units;

$pdf = (new Pdf())
    ->createdAt(new DateTimeImmutable())
    ->withPageSize(PageSize::a4())
    ->withPageOrientation(
        PageOrientation::PORTRAIT
    )
    ->inUnits(Units::MILLIMETERS)
    ->addPage();

$pdf->saveAsFile('/path/to/output.pdf');
```

</td>
</tr>
</table>

Defaults match FPDF: A4, portrait, millimeters. `createdAt()` is required before output.

---

### Fonts

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetFont('Arial', '', 12);
```

FPDF shipped with a handful of core fonts as PHP arrays. Custom TTF fonts required a separate conversion step.

</td>
<td>

```php
use Stanko\Pdf\Fonts\OpenSansBold;
use Stanko\Pdf\Fonts\OpenSansRegular;

// Load fonts once (before use)
$pdf = $pdf
    ->loadFont(OpenSansRegular::points(12))
    ->loadFont(OpenSansBold::points(16));

// Select font before drawing
$pdf = $pdf->withFont(
    OpenSansRegular::points(12)
);
```

</td>
</tr>
</table>

Built-in fonts: `OpenSansRegular`, `OpenSansBold`, `OpenSansCondensedBold`, `OpenSansSemiCondensedBold`. To use your own TTF file, implement `FontInterface`:

```php
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
```

---

### Drawing a cell

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(
    190, 10, 'Hello World',
    1, 1, 'C', true
);
```

</td>
<td>

```php
$pdf = $pdf
    ->withFont(OpenSansBold::points(16))
    ->withWidth(190)
    ->withHeight(10)
    ->drawCell(
        'Hello World',
        1,
        1,
        'C',
        true,
    );
```

</td>
</tr>
</table>

Width and height are set via builder methods and persist across subsequent `drawCell` calls until changed.

---

### Multi-line cells

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->MultiCell(
    190, 10,
    "Line one\nLine two",
    1, 'L', false
);
```

</td>
<td>

```php
use Stanko\Pdf\CellBorder;

$pdf = $pdf
    ->withWidth(190)
    ->drawMultiCell(
        10,
        "Line one\nLine two",
        CellBorder::withAllSides(),
        'L',
        false
    );
```

</td>
</tr>
</table>

`CellBorder` replaces the border string/integer with an explicit value object:

```php
CellBorder::withAllSides()   // all four sides
CellBorder::none()           // no border
CellBorder::top()            // top only
CellBorder::left()           // left only
// etc.
```

---

### Colors

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetDrawColor(255, 0, 0);
$pdf->SetFillColor(255, 255, 0);
$pdf->SetTextColor(0, 0, 0);
```

</td>
<td>

```php
use Stanko\Pdf\Color;

$pdf = $pdf
    ->withDrawColor(
        Color::fromRgb(255, 0, 0)
    )
    ->withFillColor(
        Color::fromRgb(255, 255, 0)
    )
    ->withTextColor(
        Color::fromRgb(0, 0, 0)
    );
```

</td>
</tr>
</table>

---

### Graphics

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetLineWidth(2);
$pdf->Line(10, 10, 100, 100);
$pdf->Rect(50, 50, 80, 40, 'DF');
```

</td>
<td>

```php
use Stanko\Pdf\RectangleStyle;

$pdf = $pdf
    ->withLineWidth(2)
    ->drawLine(10, 10, 100, 100)
    ->drawRectangle(
        50, 50, 80, 40,
        RectangleStyle::FILLED_AND_BORDERED
    );
```

</td>
</tr>
</table>

`RectangleStyle` values: `BORDERED`, `FILLED`, `FILLED_AND_BORDERED`.

---

### Images

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->Image(
    '/path/to/image.png',
    10, 10, 50, 0
);
```

</td>
<td>

```php
// Auto-size at current position
$pdf = $pdf->withImage(
    '/path/to/image.png'
);

// With position and explicit dimensions
$pdf = $pdf->withImage(
    '/path/to/image.png',
    10, 10, 50, 30
);
```

</td>
</tr>
</table>

Supported formats: JPG, PNG (including transparency). High-DPI rendering is controlled via `withDpi(int)`.

---

### Text output

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->Write(5, 'Flowing text');
$pdf->Write(
    5, 'With link',
    'https://example.com'
);
$pdf->Text(20, 100, 'Absolute position');
```

</td>
<td>

```php
// Flowing text
$pdf = $pdf->writeText(5, 'Flowing text');

// With a hyperlink
$pdf = $pdf->writeText(
    5, 'With link',
    'https://example.com'
);

// Absolute position
$pdf = $pdf->writeString(
    20, 100, 'Absolute position'
);
```

</td>
</tr>
</table>

---

### Positioning

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetX(50);
$pdf->SetY(100);
$pdf->Ln(10);
```

</td>
<td>

```php
$pdf = $pdf
    ->atX(50)
    ->atY(100)
    ->lowerBy(10);

// Relative movement
$pdf = $pdf->rightwardBy(5);

// Move to start of next line
$pdf = $pdf->onNextRow();
```

</td>
</tr>
</table>

---

### Margins

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetMargins(20, 15, 20);
$pdf->SetTopMargin(15);
```

</td>
<td>

```php
$pdf = $pdf
    ->withLeftMargin(20)
    ->withTopMargin(15)
    ->withRightMargin(20);
```

</td>
</tr>
</table>

---

### Page breaks

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetAutoPageBreak(false);
```

</td>
<td>

```php
$pdf = $pdf->withAutomaticPageBreaking(15);
$pdf = $pdf->withoutAutomaticPageBreaking();
```

</td>
</tr>
</table>

---

### Page numbering

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->AliasNbPages('{nb}');
// ...later in a cell:
$pdf->Cell(
    0, 10,
    'Page ' . $pdf->PageNo() . '/{nb}'
);
```

</td>
<td>

```php
// Must be called before loadFont()
$pdf = $pdf
    ->withAliasForTotalNumberOfPages('{nb}');
// ...later in a cell:
$pageInfo = $pdf->getCurrentPageNumber();
$pdf = $pdf->drawCell(
    'Page ' . $pageInfo . '/{nb}'
);
```

</td>
</tr>
</table>

---

### Metadata

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
$pdf->SetTitle('My Document');
$pdf->SetAuthor('Jane Smith');
$pdf->SetSubject('Annual report');
$pdf->SetKeywords('report, annual');
$pdf->SetCreator('My App');
```

</td>
<td>

```php
$pdf = $pdf
    ->withTitle('My Document')
    ->byAuthor('Jane Smith')
    ->withSubject('Annual report')
    ->withKeywords('report, annual')
    ->createdBy('My App');
```

</td>
</tr>
</table>

---

### Output

<table>
<tr><th>FPDF / tFPDF</th><th>stanko/pdf</th></tr>
<tr>
<td>

```php
// inline browser display
$pdf->Output();
// force download
$pdf->Output('D', 'file.pdf');
// save to disk
$pdf->Output('F', '/path/to.pdf');
// return as string
$content = $pdf->Output('S');
```

</td>
<td>

```php
// inline browser display
$pdf->toStandardOutput('file.pdf');
// force download
$pdf->downloadFile('file.pdf');
// save to disk
$pdf->saveAsFile('/path/to.pdf');
// return as string
$content = $pdf->toString();
```

</td>
</tr>
</table>

---

## Complete example

```php
use DateTimeImmutable;
use Stanko\Pdf\CellBorder;
use Stanko\Pdf\Color;
use Stanko\Pdf\Fonts\OpenSansBold;
use Stanko\Pdf\Fonts\OpenSansRegular;
use Stanko\Pdf\Pdf;
use Stanko\Pdf\RectangleStyle;

$pdf = (new Pdf())
    ->createdAt(new DateTimeImmutable())
    ->withTitle('Invoice #1042')
    ->byAuthor('Acme Corp')
    ->withAliasForTotalNumberOfPages('{nb}')
    ->loadFont(OpenSansRegular::points(11))
    ->loadFont(OpenSansBold::points(14))
    ->addPage()

    // Header
    ->withFont(OpenSansBold::points(14))
    ->withFillColor(Color::fromRgb(30, 80, 160))
    ->withTextColor(Color::fromRgb(255, 255, 255))
    ->withWidth(190)->withHeight(12)
    ->drawCell('Invoice #1042', 0, 1, 'C', true)

    // Column headers
    ->withFont(OpenSansRegular::points(11))
    ->withTextColor(Color::fromRgb(0, 0, 0))
    ->withWidth(95)->withHeight(8)
    ->drawCell('Description', 1, 0, 'L')
    ->withWidth(95)
    ->drawCell('Amount', 1, 1, 'R')

    // Row
    ->drawCell('Website redesign', 1, 0, 'L')
    ->withWidth(95)
    ->drawCell('€ 4,800.00', 1, 1, 'R')

    // Footer
    ->withDrawColor(Color::fromRgb(30, 80, 160))
    ->withLineWidth(0.5)
    ->drawLine(10, 280, 200, 280)
    ->withFont(OpenSansRegular::points(9))
    ->withTextColor(Color::fromRgb(100, 100, 100))
    ->atX(10)->atY(283)
    ->writeText(5, 'Page 1 of {nb}');

$pdf->saveAsFile('/tmp/invoice.pdf');
```

## Development

```bash
composer test
```

Runs PHPStan (max level), PHPUnit, and PHP-CS-Fixer.
