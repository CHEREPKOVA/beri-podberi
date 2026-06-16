<?php

namespace App\Services\Catalog;

use App\Models\SystemSetting;

class CatalogSearchSettings
{
    public static function minQueryLength(): int
    {
        $min = (int) SystemSetting::getActiveParsed('catalog.search_min_query_length', 2);

        return max(1, min(5, $min));
    }

    public static function suggestLimit(): int
    {
        $limit = (int) SystemSetting::getActiveParsed('catalog.search_suggest_limit', 5);

        return max(3, min(20, $limit));
    }

    public static function loggingEnabled(): bool
    {
        return (bool) SystemSetting::getActiveParsed('catalog.search_logging_enabled', true);
    }

    public static function popularSearchLimit(): int
    {
        $limit = (int) SystemSetting::getActiveParsed('catalog.popular_search_limit', 5);

        return max(1, min(10, $limit));
    }

    public static function popularSearchDays(): int
    {
        $days = (int) SystemSetting::getActiveParsed('catalog.popular_search_days', 30);

        return max(1, min(365, $days));
    }
}
