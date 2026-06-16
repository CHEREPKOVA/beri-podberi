<?php

namespace App\Services\Catalog;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogSearchLogService
{
    public function log(?User $user, ?string $roleSlug, ?int $regionId, string $query, int $resultsCount): void
    {
        if (! CatalogSearchSettings::loggingEnabled()) {
            return;
        }

        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return;
        }

        $normalized = $this->normalizeQuery($query);
        if ($normalized === '') {
            return;
        }

        DB::table('catalog_search_logs')->insert([
            'user_id' => $user?->id,
            'role_slug' => $roleSlug,
            'region_id' => $regionId,
            'query' => mb_substr($query, 0, 255),
            'query_normalized' => mb_substr($normalized, 0, 255),
            'results_count' => max(0, $resultsCount),
            'created_at' => now(),
        ]);

        $this->forgetPopularCache($roleSlug, $regionId);
    }

    /**
     * @return list<array{query: string, count: int}>
     */
    public function popularQueries(?string $roleSlug, ?int $regionId): array
    {
        $limit = CatalogSearchSettings::popularSearchLimit();
        $days = CatalogSearchSettings::popularSearchDays();
        $cacheKey = $this->popularCacheKey($roleSlug, $regionId, $limit, $days);

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($roleSlug, $regionId, $limit, $days): array {
            $since = now()->subDays($days);

            $rows = DB::table('catalog_search_logs')
                ->select('query_normalized', DB::raw('MAX(query) as query'), DB::raw('COUNT(*) as aggregate'))
                ->where('created_at', '>=', $since)
                ->when($roleSlug !== null, fn ($q) => $q->where('role_slug', $roleSlug))
                ->when($regionId !== null, fn ($q) => $q->where('region_id', $regionId))
                ->groupBy('query_normalized')
                ->orderByDesc('aggregate')
                ->limit($limit)
                ->get();

            return $rows
                ->map(static fn ($row): array => [
                    'query' => (string) $row->query,
                    'count' => (int) $row->aggregate,
                ])
                ->values()
                ->all();
        });
    }

    private function normalizeQuery(string $query): string
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');

        return preg_replace('/\s+/u', ' ', $normalized) ?? '';
    }

    private function popularCacheKey(?string $roleSlug, ?int $regionId, int $limit, int $days): string
    {
        return sprintf(
            'catalog.popular_searches:%s:%s:%d:%d',
            $roleSlug ?? 'any',
            $regionId ?? 'any',
            $limit,
            $days,
        );
    }

    private function forgetPopularCache(?string $roleSlug, ?int $regionId): void
    {
        $limit = CatalogSearchSettings::popularSearchLimit();
        $days = CatalogSearchSettings::popularSearchDays();
        Cache::forget($this->popularCacheKey($roleSlug, $regionId, $limit, $days));
    }
}
