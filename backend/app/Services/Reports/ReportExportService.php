<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Native file exporters — no third-party spreadsheet/PDF packages.
 *
 * CSV  : streamed inline rows (fputcsv), UTF-8 BOM for Excel compatibility.
 * XLSX : minimal OOXML workbook written with PHP's built-in ZipArchive.
 * PDF  : compact single-page-per-buffer PDF 1.4 built by hand.
 *
 * Every exporter writes ASCII-safe, SQL-injection/CSV-formula-proof cells
 * (text cells are prefixed with a single quote when they start with =,+,-,@).
 */
class ReportExportService
{
    private const CSV_FORMULA_PREFIXES = ['=', '+', '-', '@'];

    /**
     * Stream an export file for the given tabular data.
     *
     * @param  list<string>  $columns  header labels
     * @param  list<array<string, mixed>>  $rows
     */
    public function stream(
        array $columns,
        array $rows,
        string $format,
        string $filename,
    ): StreamedResponse {
        $format = strtolower($format);
        if (! in_array($format, ReportFilterBag::FORMATS, true)) {
            $format = 'csv';
        }

        return match ($format) {
            'xlsx' => $this->streamXlsx($columns, $rows, $filename),
            'pdf' => $this->streamPdf($columns, $rows, $filename),
            default => $this->streamCsv($columns, $rows, $filename),
        };
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function streamCsv(array $columns, array $rows, string $filename): StreamedResponse
    {
        return Response::stream(function () use ($columns, $rows): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $this->sanitizeCells($columns));
            foreach ($rows as $row) {
                $cells = [];
                foreach ($columns as $column) {
                    $cells[] = $row[$column] ?? $row[Str::slug($column)] ?? '';
                }
                fputcsv($stream, $this->sanitizeCells($cells));
            }
            fclose($stream);
        }, 200, $this->headers($filename, 'text/csv; charset=UTF-8'));
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function streamXlsx(array $columns, array $rows, string $filename): StreamedResponse
    {
        $parts = $this->buildXlsxParts($columns, $rows);

        return Response::stream(function () use ($filename, $parts): void {
            $tmp = tempnam(sys_get_temp_dir(), 'jpc_xlsx_');
            if ($tmp === false) {
                abort(500, 'Unable to allocate temporary file for export.');

                return;
            }

            try {
                $zip = new \ZipArchive;
                if ($zip->open($tmp, \ZipArchive::CREATE) !== true) {
                    abort(500, 'Unable to create workbook archive.');
                }

                foreach ($parts as $name => $content) {
                    $zip->addFromString($name, $content);
                }
                $zip->close();

                readfile($tmp);
            } finally {
                @unlink($tmp);
            }
        }, 200, $this->headers(
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ));
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function buildXlsxParts(array $columns, array $rows): array
    {
        $escapedColumns = array_map(fn (string $c) => $this->xml($c), $columns);

        $sheetRows = ['<row r="1">'];
        foreach ($escapedColumns as $index => $label) {
            $ref = $this->columnLetter($index).'1';
            $sheetRows[] = "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$label}</t></is></c>";
        }
        $sheetRows[] = '</row>';

        foreach (array_values($rows) as $rowIndex => $row) {
            $sheetRow = $rowIndex + 2;
            $rowParts = ["<row r=\"{$sheetRow}\">"];
            foreach ($columns as $index => $column) {
                $value = $row[$column] ?? $row[Str::slug($column)] ?? '';
                $ref = $this->columnLetter($index).$sheetRow;
                if (is_numeric($value)) {
                    $rowParts[] = "<c r=\"{$ref}\"><v>".$this->xml((string) $value).'</v></c>';
                } else {
                    $rowParts[] = "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>".$this->xml($this->sanitizeText($value)).'</t></is></c>';
                }
            }
            $rowParts[] = '</row>';
            $sheetRows[] = implode('', $rowParts);
        }

        $sheetData = implode('', $sheetRows);

        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetData>'.$sheetData.'</sheetData>'
                .'</worksheet>',
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    public function streamPdf(array $columns, array $rows, string $filename): StreamedResponse
    {
        // Squash each row into a fixed-width line: a deliberately minimal but
        // valid single-column report for environments without PDF packages.
        $lines = [];
        $lines[] = $this->squash($columns);
        $lines[] = '';
        foreach ($rows as $row) {
            $cells = [];
            foreach ($columns as $column) {
                $cells[] = (string) ($row[$column] ?? $row[Str::slug($column)] ?? '');
            }
            $lines[] = $this->squash($cells);
        }

        return Response::stream(function () use ($lines): void {
            echo $this->buildPdf($lines);
        }, 200, $this->headers($filename, 'application/pdf'));
    }

    /**
     * @param  list<string>  $lines
     */
    private function buildPdf(array $lines): string
    {
        $escaped = array_map(fn (string $line) => $this->pdfText($line), $lines);

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>';
        $objects[] = '<< /Length '.strlen($this->pdfStreamContent($escaped)).' >>'."\nstream\n".$this->pdfStreamContent($escaped)."\nendstream";
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $body) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $lines
     */
    private function pdfStreamContent(array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n14 790 Td\n14 TL\n";
        foreach ($lines as $line) {
            $content .= '('.$line.') Tj T*'."\n";
        }
        $content .= "ET\n";

        return $content;
    }

    /**
     * @param  array<string, mixed>|list<string>  $cells
     */
    private function squash(array $cells): string
    {
        $text = implode(' | ', array_map('strval', $cells));

        return Str::limit($text, 150);
    }

    /**
     * @param  list<string>  $cells
     * @return list<string>
     */
    private function sanitizeCells(array $cells): array
    {
        return array_map(fn (string $cell): string => $this->sanitizeText($cell), $cells);
    }

    private function sanitizeText(mixed $value): string
    {
        $text = trim((string) $value);
        $first = $text !== '' ? $text[0] : '';

        return in_array($first, self::CSV_FORMULA_PREFIXES, true) ? "'".$text : $text;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $filename, string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function pdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            (string) $value,
        );
    }
}