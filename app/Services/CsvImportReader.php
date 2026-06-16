<?php

namespace App\Services;

/**
 * Чтение CSV с поддержкой BOM, кодировок и разделителей ; и ,
 */
class CsvImportReader
{
    /**
     * @return resource
     */
    public static function open(string $path)
    {
        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException('Не удалось прочитать CSV файл');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        } elseif (! mb_check_encoding($content, 'UTF-8')) {
            $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'CP1251', 'ISO-8859-5'], true);
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'Windows-1251');
        }

        $handle = fopen('php://memory', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Не удалось открыть CSV поток');
        }

        fwrite($handle, $content);
        rewind($handle);

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @return array{header: array<int, string>, delimiter: string}
     */
    public static function readHeader($handle): array
    {
        $delimiter = self::detectDelimiter($handle);
        $header = fgetcsv($handle, 0, $delimiter, escape: '\\');

        if ($header === false || $header === [null]) {
            throw new \RuntimeException('Файл пуст или неверный формат CSV');
        }

        return [
            'header' => array_map([self::class, 'normalizeHeaderColumn'], $header),
            'delimiter' => $delimiter,
        ];
    }

    /**
     * @param  resource  $handle
     */
    public static function detectDelimiter($handle): string
    {
        $position = ftell($handle);
        $line = fgets($handle);
        if ($line === false) {
            fseek($handle, $position);

            return ';';
        }

        fseek($handle, $position);

        $semicolons = substr_count($line, ';');
        $commas = substr_count($line, ',');

        return $semicolons >= $commas ? ';' : ',';
    }

    public static function normalizeHeaderColumn(string $column): string
    {
        $column = mb_strtolower(trim($column));
        $column = preg_replace('/^\x{FEFF}/u', '', $column) ?? $column;

        if (str_contains($column, '/')) {
            $column = trim(explode('/', $column, 2)[0]);
        }

        return trim($column);
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<string, list<string>>  $aliases
     * @return array<string, int>
     */
    public static function mapHeader(array $header, array $aliases): array
    {
        $map = [];

        foreach ($header as $index => $column) {
            foreach ($aliases as $field => $keys) {
                if (isset($map[$field])) {
                    continue;
                }

                foreach ($keys as $key) {
                    if ($column === $key
                        || str_starts_with($column, $key.' ')
                        || str_contains($column, $key)) {
                        $map[$field] = $index;

                        break 2;
                    }
                }
            }
        }

        return $map;
    }
}
