# A fresh PDF generator written in PHP

This project is a work-in-progress, complete rewrite of
[tFPDF](http://fpdf.org/fr/script/script92.php),
which is a modified version of [FPDF](http://www.fpdf.org/).

## Installation

```bash
composer require stanko/pdf
```

### Code quality

Whole codebase currently passes PHPStan at max level, including the tests.

The tests cover most of the features. I aim for 100% test coverage.

### Development

To run all checks and tests, execute:

```bash
composer test
```

### Current work

I am currently making the class immutable.
