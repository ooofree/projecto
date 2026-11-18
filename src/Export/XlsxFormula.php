<?php
declare(strict_types=1);

namespace ProjectO\Export;

/**
 * A cell whose value is computed by an Excel formula. The cached numeric
 * result keeps the workbook readable in viewers that do not recalculate
 * formulas on open; Excel recalculates the formula when the file is opened.
 */
final class XlsxFormula
{
    public function __construct(
        public readonly string $formula,
        public readonly int|float $cachedValue,
    ) {
    }
}
