<?php

namespace App\Services;

use ZipArchive;

/**
 * Minimal XLSX writer (no external dependencies). Outputs a valid .xlsx file from an array of rows.
 */
class SimpleXlsxWriter
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function generate(array $rows): string
    {
        $sharedStrings = [];
        $sharedStringsMap = [];
        $sheetRows = [];

        foreach ($rows as $rowIndex => $row) {
            $sheetRow = [];
            foreach ($row as $colIndex => $value) {
                $cellRef = $this->columnLetter($colIndex) . ($rowIndex + 1);
                if (is_numeric($value) && $value !== '' && $value !== null) {
                    $sheetRow[] = '<c r="' . $cellRef . '"><v>' . $this->escape((string) $value) . '</v></c>';
                } elseif ($value === null || $value === '') {
                    $sheetRow[] = '<c r="' . $cellRef . '"></c>';
                } else {
                    $str = (string) $value;
                    if (! isset($sharedStringsMap[$str])) {
                        $sharedStringsMap[$str] = count($sharedStrings);
                        $sharedStrings[] = '<si><t>' . $this->escapeXml($str) . '</t></si>';
                    }
                    $idx = $sharedStringsMap[$str];
                    $sheetRow[] = '<c r="' . $cellRef . '" t="s"><v>' . $idx . '</v></c>';
                }
            }
            $sheetRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $sheetRow) . '</row>';
        }

        $sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">' .
            implode('', $sharedStrings) .
            '</sst>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetData>' . implode('', $sheetRows) . '</sheetData>' .
            '</worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' .
            '</Types>';

        $relsRels = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>' .
            '</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Orders" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $core = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"/>';

        $zip = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create temp zip for xlsx');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->addFromString('docProps/core.xml', $core);
        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        return $content;
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = (int) floor($index / 26);
        }

        return $letter;
    }

    private function escape(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function escapeXml(string $v): string
    {
        $v = htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return str_replace(["\r", "\n"], ["&#13;", "&#10;"], $v);
    }
}
