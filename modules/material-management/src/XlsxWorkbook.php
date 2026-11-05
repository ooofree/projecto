<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use RuntimeException;
use ZipArchive;

/**
 * Builds a single-sheet Office Open XML (.xlsx) workbook without external
 * libraries. Strings are written as inline strings so no shared-string table
 * is required, and the header row is frozen and filtered.
 */
final class XlsxWorkbook
{
    private const STYLE_HEADER = 1;
    private const STYLE_BODY = 2;

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     */
    public static function build(string $sheetName, array $headers, array $rows): string
    {
        $sheetName = self::sheetName($sheetName);
        $columnCount = max(1, count($headers));
        $headers = array_pad(array_values($headers), $columnCount, '');
        $lastColumn = self::columnName($columnCount);

        $xmlRows = [self::rowXml(1, $headers, self::STYLE_HEADER)];
        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;
            $xmlRows[] = self::rowXml(
                $rowNumber,
                array_slice(array_values($row), 0, $columnCount),
                self::STYLE_BODY
            );
        }

        $columns = '';
        for ($index = 0; $index < $columnCount; $index++) {
            $columns .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="'
                . self::columnWidth($index, $headers, $rows) . '" customWidth="1"/>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . $rowNumber . '"/>'
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<cols>' . $columns . '</cols>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . $rowNumber . '"/>'
            . '</worksheet>';

        return self::package($sheetName, $sheet);
    }

    private static function package(string $sheetName, string $sheetXml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'project-o-xlsx-');
        if ($path === false) {
            throw new RuntimeException('无法创建 Excel 导出临时文件。');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('无法创建 Excel 工作簿。');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRelationships());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        if (!$zip->close()) {
            @unlink($path);
            throw new RuntimeException('无法写入 Excel 工作簿。');
        }

        $contents = file_get_contents($path);
        @unlink($path);
        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException('无法读取生成的 Excel 工作簿。');
        }

        return $contents;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xml($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEDF2EE"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFC9D3CC"/></left>'
            . '<right style="thin"><color rgb="FFC9D3CC"/></right>'
            . '<top style="thin"><color rgb="FFC9D3CC"/></top>'
            . '<bottom style="thin"><color rgb="FFC9D3CC"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function rowXml(int $rowNumber, array $values, int $style): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $reference = self::columnName($index + 1) . $rowNumber;
            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="' . $reference . '" s="' . $style . '"><v>' . self::number($value) . '</v></c>';
                continue;
            }
            $text = self::xml((string) ($value ?? ''));
            if ($text === '') {
                $cells .= '<c r="' . $reference . '" s="' . $style . '"/>';
                continue;
            }
            $cells .= '<c r="' . $reference . '" s="' . $style . '" t="inlineStr">'
                . '<is><t xml:space="preserve">' . $text . '</t></is></c>';
        }

        return '<row r="' . $rowNumber . '">' . $cells . '</row>';
    }

    private static function columnWidth(int $index, array $headers, array $rows): float
    {
        $length = mb_strlen((string) ($headers[$index] ?? ''), 'UTF-8');
        foreach ($rows as $row) {
            $value = $row[$index] ?? '';
            if (is_scalar($value)) {
                $length = max($length, mb_strlen((string) $value, 'UTF-8'));
            }
        }

        return round(min(48.0, max(9.0, $length * 1.45 + 3)), 2);
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    private static function xml(string $value): string
    {
        $sanitized = preg_replace(
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u',
            '',
            $value
        );

        return htmlspecialchars($sanitized ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function sheetName(string $name): string
    {
        $clean = preg_replace('/[\[\]:*?\/\\\\]/u', '', $name) ?? '';
        $clean = mb_substr(trim($clean), 0, 31, 'UTF-8');

        return $clean !== '' ? $clean : 'Sheet1';
    }
}
