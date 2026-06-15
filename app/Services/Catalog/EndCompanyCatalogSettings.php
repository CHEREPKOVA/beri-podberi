<?php

namespace App\Services\Catalog;

use App\Models\SystemSetting;

class EndCompanyCatalogSettings
{
    public static function requireDistributorPrice(): bool
    {
        return (bool) SystemSetting::getActiveParsed('catalog.end_company_require_distributor_price', true);
    }

    public static function requireRegionalStock(): bool
    {
        return (bool) SystemSetting::getActiveParsed('catalog.end_company_require_regional_stock', false);
    }

    public static function showUnavailableProducts(): bool
    {
        return (bool) SystemSetting::getActiveParsed('catalog.end_company_show_unavailable_products', false);
    }

    public static function showUnavailableAnalogs(): bool
    {
        return (bool) SystemSetting::getActiveParsed('catalog.end_company_show_unavailable_analogs', false);
    }

    public static function productCardRefreshSeconds(): int
    {
        $seconds = (int) SystemSetting::getActiveParsed('catalog.product_card_refresh_seconds', 60);

        return max(0, min(600, $seconds));
    }
}
