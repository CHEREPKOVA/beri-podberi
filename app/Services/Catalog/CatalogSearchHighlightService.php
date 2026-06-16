<?php

namespace App\Services\Catalog;

class CatalogSearchHighlightService
{
    /**
     * @return list<string>
     */
    public function searchTerms(?string $query): array
    {
        $query = trim((string) $query);
        if ($query === '') {
            return [];
        }

        $terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = array_values(array_filter(
            $terms,
            static fn (string $term): bool => mb_strlen($term) >= 2,
        ));

        $compact = preg_replace('/\s+/u', '', $query);
        if (mb_strlen($compact) >= 2 && ! in_array($compact, $terms, true)) {
            $terms[] = $compact;
        }

        usort($terms, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return array_values(array_unique($terms));
    }

    /**
     * Безопасная подсветка совпадений в тексте (HTML-экранирование + mark).
     */
    public function highlight(?string $text, array $terms): string
    {
        $text = (string) $text;
        if ($text === '' || $terms === []) {
            return e($text);
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            $pattern = '/'.preg_quote($term, '/').'/iu';
            $escaped = preg_replace(
                $pattern,
                '<mark class="catalog-search-hit bg-yellow-200/90 dark:bg-yellow-700/40 rounded px-0.5 text-inherit font-inherit">$0</mark>',
                $escaped,
            ) ?? $escaped;
        }

        return $escaped;
    }
}
