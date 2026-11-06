<?php
declare(strict_types=1);

namespace ProjectO\Modules\MaterialManagement;

use RuntimeException;
use ZipArchive;

/**
 * Builds Office Open XML (.xlsx) workbooks without external libraries.
 *
 * Two layouts are supported:
 *  - plain: header on row 1, frozen header row, auto filter;
 *  - report (title/meta options set): merged bold title, metadata lines, a
 *    blank spacer row and the styled header block, mirroring the legacy
 *    "样品信息追踪表" export (white bold header on #4F81BD, thin black grid,
 *    vertical centering). Images are embedded in their cell through a drawing
 *    part and carry an optional hyperlink, like the legacy export.
 */
final class XlsxWorkbook
{
    private const STYLE_DEFAULT = 0;
    private const STYLE_TITLE = 1;
    private const STYLE_HEADER = 2;
    private const STYLE_BODY = 3;

    private const TITLE_ROW_HEIGHT = 30;
    private const IMAGE_ROW_HEIGHT = 85;

    private const IMAGE_OFFSET_X_EMU = 142875; // 15 px
    private const IMAGE_OFFSET_Y_EMU = 47625;  // 5 px
    private const IMAGE_END_X_EMU = 1000125;   // 105 px
    private const IMAGE_END_Y_EMU = 762000;    // 80 px
    private const IMAGE_WIDTH_EMU = 857250;    // 90 px
    private const IMAGE_HEIGHT_EMU = 714375;   // 75 px

    private const MEDIA_CONTENT_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
    ];

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     * @param array{
     *     title?: string,
     *     meta?: list<string>,
     *     images?: list<array{row: int, column: int, bytes: string, extension: string, hyperlink?: string}>,
     *     fixedWidths?: array<int, float>
     * } $options
     */
    public static function build(string $sheetName, array $headers, array $rows, array $options = []): string
    {
        $sheetName = self::sheetName($sheetName);
        $columnCount = max(1, count($headers));
        $headers = array_pad(array_values($headers), $columnCount, '');
        $lastColumn = self::columnName($columnCount);

        $title = is_string($options['title'] ?? null) && $options['title'] !== '' ? $options['title'] : null;
        $meta = [];
        foreach ($options['meta'] ?? [] as $line) {
            if (is_string($line) && $line !== '') {
                $meta[] = $line;
            }
        }
        $images = self::images($options['images'] ?? []);
        $fixedWidths = self::fixedWidths($options['fixedWidths'] ?? []);

        $reportLayout = $title !== null || $meta !== [];
        $headerRowNumber = $reportLayout ? count($meta) + 3 : 1;
        $firstDataRowNumber = $headerRowNumber + 1;

        $imageRows = [];
        foreach ($images as $image) {
            $imageRows[$image['row']] = true;
        }

        $xmlRows = [];
        if ($reportLayout) {
            $xmlRows[] = '<row r="1" ht="' . self::TITLE_ROW_HEIGHT . '" customHeight="1">'
                . self::cellXml(1, 0, $title, self::STYLE_TITLE)
                . '</row>';
            $metaRowNumber = 1;
            foreach ($meta as $line) {
                $metaRowNumber++;
                $xmlRows[] = '<row r="' . $metaRowNumber . '">'
                    . self::cellXml($metaRowNumber, 0, $line, self::STYLE_DEFAULT)
                    . '</row>';
            }
            $xmlRows[] = '<row r="' . ($metaRowNumber + 1) . '"/>';
        }

        $xmlRows[] = '<row r="' . $headerRowNumber . '">' . self::rowCells($headerRowNumber, $headers, self::STYLE_HEADER) . '</row>';
        $rowNumber = $headerRowNumber;
        foreach ($rows as $row) {
            $rowNumber++;
            $values = array_pad(array_slice(array_values($row), 0, $columnCount), $columnCount, '');
            $height = isset($imageRows[$rowNumber])
                ? ' ht="' . self::IMAGE_ROW_HEIGHT . '" customHeight="1"'
                : '';
            $xmlRows[] = '<row r="' . $rowNumber . '"' . $height . '>'
                . self::rowCells($rowNumber, $values, self::STYLE_BODY)
                . '</row>';
        }
        $lastRowNumber = max($rowNumber, $headerRowNumber);

        $columns = '';
        for ($index = 0; $index < $columnCount; $index++) {
            $width = $fixedWidths[$index] ?? self::columnWidth($index, $headers, $rows);
            $columns .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . $width . '" customWidth="1"/>';
        }

        $mergeRefs = [];
        if ($reportLayout) {
            $mergeRefs[] = 'A1:' . $lastColumn . '1';
            for ($metaRowNumber = 2; $metaRowNumber <= count($meta) + 1; $metaRowNumber++) {
                $mergeRefs[] = 'A' . $metaRowNumber . ':' . $lastColumn . $metaRowNumber;
            }
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRowNumber . '"/>'
            . ($reportLayout
                ? ''
                : '<sheetViews><sheetView workbookViewId="0">'
                    . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                    . '</sheetView></sheetViews>')
            . '<cols>' . $columns . '</cols>'
            . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
            . ($reportLayout ? '' : '<autoFilter ref="A1:' . $lastColumn . $lastRowNumber . '"/>')
            . ($mergeRefs === []
                ? ''
                : '<mergeCells count="' . count($mergeRefs) . '">'
                    . implode('', array_map(static fn (string $ref): string => '<mergeCell ref="' . $ref . '"/>', $mergeRefs))
                    . '</mergeCells>')
            . ($images === [] ? '' : '<drawing r:id="rId1"/>')
            . '</worksheet>';

        return self::package($sheetName, $sheet, $images);
    }

    /**
     * @param mixed $images
     * @return list<array{row: int, column: int, bytes: string, extension: string, hyperlink: ?string}>
     */
    private static function images(mixed $images): array
    {
        $normalized = [];
        foreach (is_array($images) ? $images : [] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $extension = strtolower((string) ($image['extension'] ?? ''));
            if (
                !isset($image['row'], $image['column'], $image['bytes'])
                || !is_int($image['row'])
                || !is_int($image['column'])
                || !is_string($image['bytes'])
                || $image['bytes'] === ''
                || !isset(self::MEDIA_CONTENT_TYPES[$extension])
            ) {
                continue;
            }
            $hyperlink = $image['hyperlink'] ?? null;
            $normalized[] = [
                'row' => $image['row'],
                'column' => $image['column'],
                'bytes' => $image['bytes'],
                'extension' => $extension,
                'hyperlink' => is_string($hyperlink) && $hyperlink !== '' ? $hyperlink : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $fixedWidths
     * @return array<int, float>
     */
    private static function fixedWidths(mixed $fixedWidths): array
    {
        $normalized = [];
        foreach (is_array($fixedWidths) ? $fixedWidths : [] as $index => $width) {
            if ((!is_int($width) && !is_float($width)) || !is_int($index) || $index < 0) {
                continue;
            }
            $normalized[$index] = (float) $width;
        }

        return $normalized;
    }

    /**
     * @param list<string|int|float|null> $values
     */
    private static function rowCells(int $rowNumber, array $values, int $style): string
    {
        $cells = '';
        foreach (array_values($values) as $index => $value) {
            $cells .= self::cellXml($rowNumber, $index, $value, $style);
        }

        return $cells;
    }

    private static function cellXml(int $rowNumber, int $columnIndex, string|int|float|null $value, int $style): string
    {
        $reference = self::columnName($columnIndex + 1) . $rowNumber;
        if (is_int($value) || is_float($value)) {
            return '<c r="' . $reference . '" s="' . $style . '"><v>' . self::number($value) . '</v></c>';
        }

        $text = self::xml((string) ($value ?? ''));
        if ($text === '') {
            // Keep styled empty cells so the bordered grid stays intact.
            return $style === self::STYLE_DEFAULT ? '' : '<c r="' . $reference . '" s="' . $style . '"/>';
        }

        return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr">'
            . '<is><t xml:space="preserve">' . $text . '</t></is></c>';
    }

    /**
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink: ?string}> $images
     */
    private static function package(string $sheetName, string $sheetXml, array $images): string
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

        $zip->addFromString('[Content_Types].xml', self::contentTypes($images));
        $zip->addFromString('_rels/.rels', self::rootRelationships());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        if ($images !== []) {
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', self::sheetRelationships());
            $zip->addFromString('xl/drawings/drawing1.xml', self::drawing($images));
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', self::drawingRelationships($images));
            foreach (array_values($images) as $index => $image) {
                $zip->addFromString('xl/media/image' . ($index + 1) . '.' . $image['extension'], $image['bytes']);
            }
        }

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

    /**
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink: ?string}> $images
     */
    private static function contentTypes(array $images): string
    {
        $defaults = '';
        foreach (array_keys(self::mediaExtensions($images)) as $extension) {
            $defaults .= '<Default Extension="' . $extension . '" ContentType="' . self::MEDIA_CONTENT_TYPES[$extension] . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $defaults
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($images === []
                ? ''
                : '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>')
            . '</Types>';
    }

    /**
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink: ?string}> $images
     * @return array<string, string>
     */
    private static function mediaExtensions(array $images): array
    {
        $extensions = [];
        foreach ($images as $image) {
            $extensions[$image['extension']] = self::MEDIA_CONTENT_TYPES[$image['extension']];
        }

        return $extensions;
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

    private static function sheetRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink: ?string}> $images
     */
    private static function drawing(array $images): string
    {
        $anchors = '';
        foreach (array_values($images) as $index => $image) {
            $shapeId = $index + 2;
            $hyperlink = $image['hyperlink'] === null
                ? ''
                : '<a:hlinkClick r:id="rIdH' . $index . '"/>';
            $anchors .= '<xdr:twoCellAnchor editAs="oneCell">'
                . '<xdr:from>'
                . '<xdr:col>' . $image['column'] . '</xdr:col>'
                . '<xdr:colOff>' . self::IMAGE_OFFSET_X_EMU . '</xdr:colOff>'
                . '<xdr:row>' . ($image['row'] - 1) . '</xdr:row>'
                . '<xdr:rowOff>' . self::IMAGE_OFFSET_Y_EMU . '</xdr:rowOff>'
                . '</xdr:from>'
                . '<xdr:to>'
                . '<xdr:col>' . $image['column'] . '</xdr:col>'
                . '<xdr:colOff>' . self::IMAGE_END_X_EMU . '</xdr:colOff>'
                . '<xdr:row>' . ($image['row'] - 1) . '</xdr:row>'
                . '<xdr:rowOff>' . self::IMAGE_END_Y_EMU . '</xdr:rowOff>'
                . '</xdr:to>'
                . '<xdr:pic>'
                . '<xdr:nvPicPr>'
                . '<xdr:cNvPr id="' . $shapeId . '" name="Sample Image ' . $index . '" descr="样品图片">' . $hyperlink . '</xdr:cNvPr>'
                . '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr>'
                . '</xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rIdM' . $index . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr>'
                . '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . self::IMAGE_WIDTH_EMU . '" cy="' . self::IMAGE_HEIGHT_EMU . '"/></a:xfrm>'
                . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
                . '</xdr:spPr>'
                . '</xdr:pic>'
                . '<xdr:clientData/>'
                . '</xdr:twoCellAnchor>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $anchors
            . '</xdr:wsDr>';
    }

    /**
     * @param list<array{row: int, column: int, bytes: string, extension: string, hyperlink: ?string}> $images
     */
    private static function drawingRelationships(array $images): string
    {
        $relationships = '';
        foreach (array_values($images) as $index => $image) {
            $relationships .= '<Relationship Id="rIdM' . $index . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                . ' Target="../media/image' . ($index + 1) . '.' . $image['extension'] . '"/>';
            if ($image['hyperlink'] !== null) {
                $relationships .= '<Relationship Id="rIdH' . $index . '"'
                    . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"'
                    . ' Target="' . self::xml($image['hyperlink']) . '" TargetMode="External"/>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
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
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF4F81BD"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF000000"/></left>'
            . '<right style="thin"><color rgb="FF000000"/></right>'
            . '<top style="thin"><color rgb="FF000000"/></top>'
            . '<bottom style="thin"><color rgb="FF000000"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
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
            '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
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
