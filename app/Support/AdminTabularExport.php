<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;

class AdminTabularExport
{
    /**
     * @param  list<string>  $headers
     * @param  callable(): \Generator<list<string|int|float|null>>  $rows
     */
    public static function stream(string $basename, string $format, array $headers, callable $rows): StreamedResponse
    {
        return match ($format) {
            'xml' => self::xml($basename, $headers, $rows),
            'excel', 'xlsx' => self::excel($basename, $headers, $rows),
            default => self::csv($basename, $headers, $rows),
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(): \Generator<list<string|int|float|null>>  $rows
     */
    private static function csv(string $basename, array $headers, callable $rows): StreamedResponse
    {
        $filename = self::filename($basename, 'csv');

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            foreach ($rows() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(): \Generator<list<string|int|float|null>>  $rows
     */
    private static function xml(string $basename, array $headers, callable $rows): StreamedResponse
    {
        $filename = self::filename($basename, 'xml');

        return response()->streamDownload(function () use ($headers, $rows): void {
            $writer = new XMLWriter;
            $writer->openURI('php://output');
            $writer->startDocument('1.0', 'UTF-8');
            $writer->startElement('root');

            foreach ($rows() as $row) {
                $writer->startElement('item');
                foreach ($headers as $index => $header) {
                    $key = self::xmlKey($header);
                    $writer->writeElement($key, (string) ($row[$index] ?? ''));
                }
                $writer->endElement();
            }

            $writer->endElement();
            $writer->endDocument();
            $writer->flush();
        }, $filename, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  callable(): \Generator<list<string|int|float|null>>  $rows
     */
    private static function excel(string $basename, array $headers, callable $rows): StreamedResponse
    {
        $filename = self::filename($basename, 'xlsx');

        return response()->streamDownload(function () use ($headers, $rows): void {
            echo '<?xml version="1.0"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
            echo 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
            echo '<Worksheet ss:Name="Export"><Table>';

            echo '<Row>';
            foreach ($headers as $header) {
                echo '<Cell><Data ss:Type="String">'.self::escapeSpreadsheet($header).'</Data></Cell>';
            }
            echo '</Row>';

            foreach ($rows() as $row) {
                echo '<Row>';
                foreach ($row as $value) {
                    $type = is_numeric($value) && $value !== '' && $value !== null ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="'.$type.'">'.self::escapeSpreadsheet((string) ($value ?? '')).'</Data></Cell>';
                }
                echo '</Row>';
            }

            echo '</Table></Worksheet></Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function filename(string $basename, string $extension): string
    {
        return $basename.'-'.now()->format('Y-m-d-His').'.'.$extension;
    }

    private static function escapeSpreadsheet(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function xmlKey(string $header): string
    {
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $header) ?? 'field');

        return trim($key, '_') ?: 'field';
    }
}
