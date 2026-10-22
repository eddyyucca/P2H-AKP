<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ekspor CSV yang langsung terbuka rapi di Excel Indonesia (BOM UTF-8, pemisah titik koma). */
class Csv
{
    /** Sel yang diawali = + - @ dianggap rumus oleh Excel; diberi apostrof agar tampil sebagai teks. */
    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text) ? "'".$text : $text;
    }

    /**
     * @param  array<int, string>  $headings
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
