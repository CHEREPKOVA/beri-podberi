<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    private const VERSION_KEY = 'catalog.cache.version';

    public function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public function versionedKey(string $baseKey): string
    {
        return $baseKey.':v'.$this->version();
    }

    public function bump(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }
}
