<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ParsesCatalogAttributeFilters
{
    /** Парсит параметры фильтров атрибутов из запроса (attr[id], attr[id][], attr[id][min/max]). */
    protected function parseCatalogAttributeFilters(Request $request): array
    {
        $attr = $request->input('attr', $request->input('attributes', []));
        if (! is_array($attr)) {
            return [];
        }
        $out = [];
        foreach ($attr as $id => $value) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            if (is_array($value)) {
                if (array_key_exists('min', $value) || array_key_exists('max', $value)) {
                    $min = isset($value['min']) ? trim((string) $value['min']) : '';
                    $max = isset($value['max']) ? trim((string) $value['max']) : '';
                    if ($min !== '' || $max !== '') {
                        $out[$id] = ['min' => $min, 'max' => $max];
                    }

                    continue;
                }
                $filtered = array_values(array_filter(array_map('trim', $value)));
                if ($filtered !== []) {
                    $out[$id] = $filtered;
                }
            } else {
                $value = trim((string) $value);
                if ($value !== '') {
                    $out[$id] = $value;
                }
            }
        }

        return $out;
    }
}
