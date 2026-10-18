<?php

namespace App\Services\Discount;

use App\Models\DistributorDiscountSet;
use App\Models\DistributorDiscountSetLog;
use App\Models\DistributorProduct;
use App\Models\DistributorProfile;
use App\Models\EndCompanyProfile;
use App\Models\PlatformOrder;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DistributorDiscountSetService
{
    /**
     * @return array<string, mixed>
     */
    public function paginate(DistributorProfile $profile, Request $request): array
    {
        $this->syncExpired($profile);

        $query = DistributorDiscountSet::query()->forDistributor((int) $profile->id);

        if ($request->filled('search')) {
            $this->applySearch($query, $profile, (string) $request->input('search'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $this->applyStatusFilter($query, (string) $request->input('status'));
        }

        if ($request->filled('period')) {
            $this->applyPeriodFilter($query, (string) $request->input('period'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $query->where(function ($inner) use ($categoryId): void {
                $inner->where('apply_to_all_products', true)
                    ->orWhereJsonContains('category_ids', $categoryId)
                    ->orWhereJsonContains('category_ids', (string) $categoryId);
            });
        }

        if ($request->filled('company_id')) {
            $companyId = (int) $request->input('company_id');
            $query->where(function ($inner) use ($companyId): void {
                $inner->whereIn('type', [
                    DistributorDiscountSet::TYPE_PERSONAL,
                    DistributorDiscountSet::TYPE_MASS,
                ])->where(function ($audience) use ($companyId): void {
                    $audience->whereJsonContains('company_ids', $companyId)
                        ->orWhereJsonContains('company_ids', (string) $companyId);
                });
            });
        }

        $sort = (string) $request->input('sort', 'updated_at');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = match ($sort) {
            'name' => 'name',
            'type' => 'type',
            'status' => 'status',
            'period' => 'starts_at',
            default => 'updated_at',
        };
        $query->orderBy($sortColumn, $dir)->orderByDesc('id');

        $sets = $query->paginate(20)->withQueryString();

        return [
            'sets' => $sets,
            'rows' => $sets->getCollection()->map(fn (DistributorDiscountSet $set) => $this->listRow($profile, $set)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(DistributorProfile $profile, User $user, array $data): DistributorDiscountSet
    {
        $payload = $this->normalizePayload($data);

        return DB::transaction(function () use ($profile, $user, $payload): DistributorDiscountSet {
            $set = DistributorDiscountSet::query()->create(array_merge($payload, [
                'distributor_profile_id' => $profile->id,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]));
            $this->log($set, $user, 'created', [
                'name' => [null, $set->name],
                'type' => [null, $set->type],
                'status' => [null, $set->status],
                'discount_percent' => [null, $set->discount_percent],
                'discount_amount' => [null, $set->discount_amount],
            ]);

            return $set;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DistributorDiscountSet $set, User $user, array $data): DistributorDiscountSet
    {
        $payload = $this->normalizePayload($data);
        $before = $this->snapshot($set);

        return DB::transaction(function () use ($set, $user, $payload, $before): DistributorDiscountSet {
            $set->fill(array_merge($payload, ['updated_by_user_id' => $user->id]));
            $set->save();
            $changes = $this->diff($before, $this->snapshot($set));
            if ($changes !== []) {
                $this->log($set, $user, 'updated', $changes);
            }

            return $set->refresh();
        });
    }

    public function toggle(DistributorDiscountSet $set, User $user): DistributorDiscountSet
    {
        $next = $set->status === DistributorDiscountSet::STATUS_ACTIVE
            ? DistributorDiscountSet::STATUS_INACTIVE
            : DistributorDiscountSet::STATUS_ACTIVE;

        $from = $set->status;
        $set->status = $next;
        $set->updated_by_user_id = $user->id;
        $set->save();
        $this->log($set, $user, $next === DistributorDiscountSet::STATUS_ACTIVE ? 'activated' : 'deactivated', [
            'status' => [$from, $next],
        ]);

        return $set;
    }

    public function duplicate(DistributorDiscountSet $set, User $user): DistributorDiscountSet
    {
        $copy = $set->replicate(['created_by_user_id', 'updated_by_user_id']);
        $copy->name = $set->name.' (копия)';
        $copy->status = DistributorDiscountSet::STATUS_INACTIVE;
        $copy->source = DistributorDiscountSet::SOURCE_MANUAL;
        $copy->created_by_user_id = $user->id;
        $copy->updated_by_user_id = $user->id;
        $copy->save();
        $this->log($copy, $user, 'copied', [
            'source_id' => [$set->id, $copy->id],
            'name' => [$set->name, $copy->name],
        ]);

        return $copy;
    }

    public function delete(DistributorDiscountSet $set, User $user): void
    {
        if ($this->isUsedInActiveOrders($set)) {
            throw new InvalidArgumentException('Набор нельзя удалить: он используется в текущих заказах.');
        }

        $this->log($set, $user, 'deleted', ['name' => [$set->name, null]]);
        $set->delete();
    }

    /**
     * @param  list<int>  $ids
     * @return array{disabled:int, deleted:int, skipped:int}
     */
    public function bulk(DistributorProfile $profile, User $user, array $ids, string $action): array
    {
        $sets = DistributorDiscountSet::query()
            ->forDistributor((int) $profile->id)
            ->whereIn('id', $ids)
            ->get();

        $disabled = 0;
        $deleted = 0;
        $skipped = 0;

        foreach ($sets as $set) {
            if ($action === 'disable') {
                if ($set->status !== DistributorDiscountSet::STATUS_INACTIVE) {
                    $set->status = DistributorDiscountSet::STATUS_INACTIVE;
                    $set->updated_by_user_id = $user->id;
                    $set->save();
                    $this->log($set, $user, 'deactivated', ['status' => [DistributorDiscountSet::STATUS_ACTIVE, DistributorDiscountSet::STATUS_INACTIVE]]);
                    $disabled++;
                }
            } elseif ($action === 'delete') {
                try {
                    $this->delete($set, $user);
                    $deleted++;
                } catch (InvalidArgumentException) {
                    $skipped++;
                }
            }
        }

        return compact('disabled', 'deleted', 'skipped');
    }

    public function export(DistributorProfile $profile): StreamedResponse
    {
        $sets = DistributorDiscountSet::query()
            ->forDistributor((int) $profile->id)
            ->orderBy('name')
            ->get();

        $filename = 'discount-sets-'.now()->format('Ymd-His').'.xls';

        return response()->streamDownload(function () use ($profile, $sets): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, [
                'Название', 'Тип', 'Размер', 'Период', 'Статус', 'Товары', 'Клиенты',
                'Изменён', 'Источник',
            ], ';');
            foreach ($sets as $set) {
                $row = $this->listRow($profile, $set);
                fputcsv($file, [
                    $set->name,
                    $set->typeLabel(),
                    $set->sizeLabel(),
                    $set->periodLabel(),
                    $set->statusLabel(),
                    $row['product_count'],
                    $row['client_label'],
                    $set->updated_at?->format('d.m.Y H:i'),
                    $set->sourceLabel(),
                ], ';');
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function isUsedInActiveOrders(DistributorDiscountSet $set): bool
    {
        return $set->orderItems()
            ->whereHas('order', function ($query): void {
                $query->whereNotIn('status', [
                    PlatformOrder::STATUS_COMPLETED,
                    PlatformOrder::STATUS_REJECTED,
                ]);
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizePayload(array $data): array
    {
        $type = (string) ($data['type'] ?? DistributorDiscountSet::TYPE_PRODUCTS);
        $valueKind = (string) ($data['value_kind'] ?? DistributorDiscountSet::VALUE_PERCENT);
        $isIndefinite = (bool) ($data['is_indefinite'] ?? false);
        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $isIndefinite ? null : ($data['ends_at'] ?? null);

        $applyAll = $this->resolveApplyAll($type, $data);
        $audience = $this->resolveAudience($type, $data);

        return [
            'name' => trim((string) $data['name']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'type' => $type,
            'value_kind' => $valueKind,
            'status' => (string) ($data['status'] ?? DistributorDiscountSet::STATUS_INACTIVE),
            'discount_percent' => $valueKind === DistributorDiscountSet::VALUE_PERCENT && ($data['discount_percent'] ?? '') !== ''
                ? (float) $data['discount_percent']
                : null,
            'discount_amount' => $valueKind === DistributorDiscountSet::VALUE_FIXED && ($data['discount_amount'] ?? '') !== ''
                ? (float) $data['discount_amount']
                : null,
            'min_order_amount' => ($data['min_order_amount'] ?? '') === '' ? null : (float) $data['min_order_amount'],
            'min_quantity' => ($data['min_quantity'] ?? '') === '' ? null : (int) $data['min_quantity'],
            'warehouse_ids' => $this->intArray($data['warehouse_ids'] ?? []),
            'do_not_stack' => (bool) ($data['do_not_stack'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
            'source' => (string) ($data['source'] ?? DistributorDiscountSet::SOURCE_MANUAL),
            'apply_to_all_products' => $applyAll,
            'category_ids' => $type === DistributorDiscountSet::TYPE_CATEGORY || in_array($type, [DistributorDiscountSet::TYPE_PERSONAL, DistributorDiscountSet::TYPE_MASS], true)
                ? $this->intArray($data['category_ids'] ?? [])
                : [],
            'brands' => $type === DistributorDiscountSet::TYPE_BRAND || in_array($type, [DistributorDiscountSet::TYPE_PERSONAL, DistributorDiscountSet::TYPE_MASS], true)
                ? $this->stringArray($data['brands'] ?? [])
                : [],
            'product_ids' => $applyAll ? [] : $this->intArray($data['product_ids'] ?? []),
            'audience_type' => $audience,
            'company_ids' => in_array($audience, [DistributorDiscountSet::AUDIENCE_SELECTED], true)
                ? $this->intArray($data['company_ids'] ?? [])
                : [],
            'region_ids' => $audience === DistributorDiscountSet::AUDIENCE_REGION
                ? $this->intArray($data['region_ids'] ?? [])
                : [],
            'company_types' => $audience === DistributorDiscountSet::AUDIENCE_COMPANY_TYPES
                ? array_values(array_filter(array_map('strval', $data['company_types'] ?? [])))
                : [],
            'starts_at' => $startsAt ?: null,
            'ends_at' => $endsAt ?: null,
            'is_indefinite' => $isIndefinite || ! $endsAt,
            'auto_deactivate' => (bool) ($data['auto_deactivate'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formCatalog(DistributorProfile $profile, ?DistributorDiscountSet $set = null): array
    {
        $categoryTree = ProductCategory::assignableTree();

        return [
            'types' => config('distributor_discounts.types'),
            'valueKinds' => config('distributor_discounts.value_kinds'),
            'audienceTypes' => config('distributor_discounts.audience_types'),
            'companyTypes' => config('distributor_discounts.company_types'),
            'sources' => config('distributor_discounts.sources'),
            'categoryOptions' => ProductCategory::selectOptionsFromTree($categoryTree),
            'regionOptions' => Region::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'brandOptions' => $this->brandOptions($profile),
            'warehouses' => $profile->warehouses()->orderBy('name')->get(['id', 'name']),
            'selectedProducts' => $this->productOptions($profile, $set?->intList($set->product_ids) ?? []),
            'selectedCompanies' => $this->companyOptions($set?->intList($set->company_ids) ?? []),
            'coveredProducts' => $set ? $this->coveredProducts($profile, $set) : [],
            'assignedCompanies' => $set ? $this->assignedCompanies($set) : [],
        ];
    }

    /**
     * @return list<array{id:int,label:string,sku:?string,brand:?string,price:float,category:?string}>
     */
    public function searchProducts(DistributorProfile $profile, string $term, int $limit = 20): array
    {
        $term = trim($term);
        $query = DistributorProduct::query()
            ->forDistributor((int) $profile->id)
            ->with('category')
            ->where('status', DistributorProduct::STATUS_ACTIVE)
            ->orderBy('name');

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('internal_sku', 'like', $like)
                    ->orWhere('manufacturer_sku', 'like', $like)
                    ->orWhere('brand', 'like', $like);
            });
        }

        return $query->limit($limit)
            ->get()
            ->map(fn (DistributorProduct $product): array => [
                'id' => (int) $product->id,
                'label' => trim($product->name.($product->internal_sku || $product->manufacturer_sku ? ' · '.($product->internal_sku ?: $product->manufacturer_sku) : '')),
                'sku' => $product->internal_sku ?: $product->manufacturer_sku,
                'brand' => $product->brand,
                'price' => (float) ($product->retail_price ?? 0),
                'category' => $product->category?->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,inn:?string,region:?string,legal_form:?string}>
     */
    public function searchClients(string $term, int $limit = 20): array
    {
        $term = trim($term);
        $query = EndCompanyProfile::query()
            ->with(['deliveryAddresses.region', 'user'])
            ->orderBy('short_name')
            ->orderBy('full_name');

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('short_name', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('inn', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $like))
                    ->orWhereHas('deliveryAddresses.region', fn ($region) => $region->where('name', 'like', $like));
            });
        }

        return $query->limit($limit)
            ->get()
            ->map(fn (EndCompanyProfile $company): array => $this->companyRow($company))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function coveredProducts(DistributorProfile $profile, DistributorDiscountSet $set, int $limit = 80): array
    {
        $query = DistributorProduct::query()
            ->forDistributor((int) $profile->id)
            ->with('category')
            ->orderBy('name');

        if (! $set->apply_to_all_products) {
            $productIds = $set->intList($set->product_ids);
            $categoryIds = $this->expandCategoryIds($set->intList($set->category_ids));
            $brands = $set->stringList($set->brands);

            $query->where(function ($inner) use ($productIds, $categoryIds, $brands): void {
                $applied = false;
                if ($productIds !== []) {
                    $inner->orWhereIn('id', $productIds);
                    $applied = true;
                }
                if ($categoryIds !== []) {
                    $inner->orWhereIn('product_category_id', $categoryIds);
                    $applied = true;
                }
                if ($brands !== []) {
                    $inner->orWhereIn('brand', $brands);
                    $applied = true;
                }
                if (! $applied) {
                    $inner->whereRaw('1 = 0');
                }
            });
        }

        return $query->limit($limit)
            ->get()
            ->map(function (DistributorProduct $product) use ($set): array {
                $list = (float) ($product->retail_price ?? 0);
                $discounted = $this->previewUnitPrice($set, $list);

                return [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'sku' => $product->internal_sku ?: $product->manufacturer_sku,
                    'brand' => $product->brand,
                    'category' => $product->category?->name,
                    'list_price' => $list,
                    'discounted_price' => $discounted,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,inn:?string,region:?string,legal_form:?string}>
     */
    public function assignedCompanies(DistributorDiscountSet $set): array
    {
        $ids = $set->intList($set->company_ids);
        if ($ids === []) {
            return [];
        }

        return EndCompanyProfile::query()
            ->with(['deliveryAddresses.region'])
            ->whereIn('id', $ids)
            ->orderBy('short_name')
            ->get()
            ->map(fn (EndCompanyProfile $company): array => $this->companyRow($company))
            ->all();
    }

    /**
     * @return list<DistributorDiscountSet>
     */
    public function findConflicts(DistributorProfile $profile, array $payload, ?int $ignoreId = null): array
    {
        if (($payload['status'] ?? null) !== DistributorDiscountSet::STATUS_ACTIVE) {
            return [];
        }

        return DistributorDiscountSet::query()
            ->forDistributor((int) $profile->id)
            ->where('status', DistributorDiscountSet::STATUS_ACTIVE)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->filter(function (DistributorDiscountSet $set) use ($payload): bool {
                if (! $set->isCurrentlyApplicable()) {
                    return false;
                }
                if ($set->do_not_stack || ! empty($payload['do_not_stack'])) {
                    return $this->scopesOverlap($set, $payload);
                }

                return $this->scopesOverlap($set, $payload);
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<DistributorDiscountSet>  $conflicts
     */
    public function conflictMessage(array $conflicts): string
    {
        $names = collect($conflicts)->pluck('name')->filter()->take(3)->implode(', ');

        return 'Уже есть набор скидок с пересекающимися условиями. Продолжить? Пересечение: '.$names;
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(DistributorProfile $profile, DistributorDiscountSet $set): array
    {
        return [
            'set' => $set,
            'product_count' => $this->productCount($profile, $set),
            'client_count' => $set->clientCount(),
            'client_label' => $set->appliesToAllClients() && $set->clientCount() === 0
                ? 'Все'
                : (string) $set->clientCount(),
        ];
    }

    private function productCount(DistributorProfile $profile, DistributorDiscountSet $set): int
    {
        $query = DistributorProduct::query()->forDistributor((int) $profile->id);

        if ($set->apply_to_all_products) {
            return (int) $query->count();
        }

        $productIds = $set->intList($set->product_ids);
        $categoryIds = $this->expandCategoryIds($set->intList($set->category_ids));
        $brands = $set->stringList($set->brands);

        return (int) $query->where(function ($inner) use ($productIds, $categoryIds, $brands): void {
            $applied = false;
            if ($productIds !== []) {
                $inner->orWhereIn('id', $productIds);
                $applied = true;
            }
            if ($categoryIds !== []) {
                $inner->orWhereIn('product_category_id', $categoryIds);
                $applied = true;
            }
            if ($brands !== []) {
                $inner->orWhereIn('brand', $brands);
                $applied = true;
            }
            if (! $applied) {
                $inner->whereRaw('1 = 0');
            }
        })->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveApplyAll(string $type, array $data): bool
    {
        if ($type === DistributorDiscountSet::TYPE_CATEGORY) {
            return false;
        }
        if ($type === DistributorDiscountSet::TYPE_BRAND) {
            return false;
        }
        if ($type === DistributorDiscountSet::TYPE_PRODUCTS) {
            return false;
        }

        return (bool) ($data['apply_to_all_products'] ?? true)
            && empty($data['product_ids'])
            && empty($data['category_ids'])
            && empty($data['brands']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveAudience(string $type, array $data): string
    {
        if ($type === DistributorDiscountSet::TYPE_PERSONAL) {
            return DistributorDiscountSet::AUDIENCE_SELECTED;
        }
        if ($type === DistributorDiscountSet::TYPE_MASS) {
            return (string) ($data['audience_type'] ?? DistributorDiscountSet::AUDIENCE_SELECTED);
        }

        return DistributorDiscountSet::AUDIENCE_ALL;
    }

    private function applySearch($query, DistributorProfile $profile, string $term): void
    {
        $term = trim($term);
        $like = '%'.$term.'%';
        $productIds = DistributorProduct::query()
            ->forDistributor((int) $profile->id)
            ->where(function ($inner) use ($like): void {
                $inner->where('internal_sku', 'like', $like)
                    ->orWhere('manufacturer_sku', 'like', $like)
                    ->orWhere('name', 'like', $like);
            })
            ->limit(80)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $companyIds = EndCompanyProfile::query()
            ->where(function ($inner) use ($like): void {
                $inner->where('short_name', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->orWhere('inn', 'like', $like);
            })
            ->limit(80)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $query->where(function ($inner) use ($like, $productIds, $companyIds): void {
            $inner->where('name', 'like', $like)
                ->orWhere('description', 'like', $like);
            foreach ($productIds as $id) {
                $inner->orWhereJsonContains('product_ids', $id)
                    ->orWhereJsonContains('product_ids', (string) $id);
            }
            foreach ($companyIds as $id) {
                $inner->orWhereJsonContains('company_ids', $id)
                    ->orWhereJsonContains('company_ids', (string) $id);
            }
        });
    }

    private function applyStatusFilter($query, string $status): void
    {
        $today = now()->toDateString();

        if ($status === DistributorDiscountSet::STATUS_COMPLETED) {
            $query->where(function ($inner) use ($today): void {
                $inner->where('status', DistributorDiscountSet::STATUS_COMPLETED)
                    ->orWhere(function ($expired) use ($today): void {
                        $expired->where('status', DistributorDiscountSet::STATUS_ACTIVE)
                            ->where('is_indefinite', false)
                            ->whereNotNull('ends_at')
                            ->whereDate('ends_at', '<', $today);
                    });
            });

            return;
        }

        if ($status === DistributorDiscountSet::STATUS_SCHEDULED) {
            $query->where('status', DistributorDiscountSet::STATUS_ACTIVE)
                ->whereNotNull('starts_at')
                ->whereDate('starts_at', '>', $today);

            return;
        }

        if ($status === DistributorDiscountSet::STATUS_ACTIVE) {
            $query->where('status', DistributorDiscountSet::STATUS_ACTIVE)
                ->where(function ($inner) use ($today): void {
                    $inner->whereNull('starts_at')->orWhereDate('starts_at', '<=', $today);
                })
                ->where(function ($inner) use ($today): void {
                    $inner->where('is_indefinite', true)
                        ->orWhereNull('ends_at')
                        ->orWhereDate('ends_at', '>=', $today);
                });

            return;
        }

        $query->where('status', $status);
    }

    private function applyPeriodFilter($query, string $period): void
    {
        match ($period) {
            'current' => $this->applyStatusFilter($query, DistributorDiscountSet::STATUS_ACTIVE),
            'future' => $this->applyStatusFilter($query, DistributorDiscountSet::STATUS_SCHEDULED),
            'ended' => $this->applyStatusFilter($query, DistributorDiscountSet::STATUS_COMPLETED),
            default => null,
        };
    }

    private function syncExpired(DistributorProfile $profile): void
    {
        DistributorDiscountSet::query()
            ->forDistributor((int) $profile->id)
            ->where('status', DistributorDiscountSet::STATUS_ACTIVE)
            ->where('is_indefinite', false)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->update(['status' => DistributorDiscountSet::STATUS_INACTIVE]);
    }

    /**
     * @return array<int, string>
     */
    private function productOptions(DistributorProfile $profile, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DistributorProduct::query()
            ->forDistributor((int) $profile->id)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'internal_sku', 'manufacturer_sku', 'retail_price', 'brand'])
            ->mapWithKeys(fn (DistributorProduct $product) => [
                $product->id => trim($product->name.' · '.($product->internal_sku ?: $product->manufacturer_sku ?: '#'.$product->id)),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function companyOptions(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return EndCompanyProfile::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (EndCompanyProfile $company) => [
                $company->id => $company->displayName().($company->inn ? ' · ИНН '.$company->inn : ''),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function brandOptions(DistributorProfile $profile): array
    {
        return DistributorProduct::query()
            ->forDistributor((int) $profile->id)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->map(fn ($brand) => (string) $brand)
            ->all();
    }

    /**
     * @return array{id:int,name:string,inn:?string,region:?string,legal_form:?string}
     */
    private function companyRow(EndCompanyProfile $company): array
    {
        $region = $company->relationLoaded('deliveryAddresses')
            ? $company->deliveryAddresses->first()?->region?->name
            : null;

        return [
            'id' => (int) $company->id,
            'name' => $company->displayName(),
            'inn' => $company->inn,
            'region' => $region,
            'legal_form' => $company->legalFormLabel(),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function expandCategoryIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $all = $ids;
        $pending = $ids;
        while ($pending !== []) {
            $children = ProductCategory::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $new = array_values(array_diff($children, $all));
            $all = array_merge($all, $new);
            $pending = $new;
        }

        return $all;
    }

    private function previewUnitPrice(DistributorDiscountSet $set, float $list): float
    {
        if ($set->value_kind === DistributorDiscountSet::VALUE_FIXED && $set->discount_amount !== null) {
            return max(0, round($list - (float) $set->discount_amount, 2));
        }
        if ($set->discount_percent !== null) {
            return max(0, round($list * (1 - ((float) $set->discount_percent / 100)), 2));
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function scopesOverlap(DistributorDiscountSet $set, array $payload): bool
    {
        $newProducts = $this->intArray($payload['product_ids'] ?? []);
        $newCategories = $this->intArray($payload['category_ids'] ?? []);
        $newBrands = $this->stringArray($payload['brands'] ?? []);
        $newAll = (bool) ($payload['apply_to_all_products'] ?? false);
        $existingAll = $set->apply_to_all_products;

        if ($newAll || $existingAll) {
            return true;
        }
        if (array_intersect($newProducts, $set->intList($set->product_ids)) !== []) {
            return true;
        }
        if (array_intersect($newCategories, $set->intList($set->category_ids)) !== []) {
            return true;
        }
        if (array_intersect($newBrands, $set->stringList($set->brands)) !== []) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DistributorDiscountSet $set): array
    {
        return [
            'name' => $set->name,
            'type' => $set->type,
            'status' => $set->status,
            'value_kind' => $set->value_kind,
            'discount_percent' => $set->discount_percent,
            'discount_amount' => $set->discount_amount,
            'starts_at' => $set->starts_at?->toDateString(),
            'ends_at' => $set->ends_at?->toDateString(),
            'audience_type' => $set->audience_type,
            'min_order_amount' => $set->min_order_amount,
            'min_quantity' => $set->min_quantity,
            'priority' => $set->priority,
            'product_ids' => $set->intList($set->product_ids),
            'company_ids' => $set->intList($set->company_ids),
            'category_ids' => $set->intList($set->category_ids),
            'brands' => $set->stringList($set->brands),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{0:mixed,1:mixed}>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) != $value) {
                $changes[$key] = [$this->stringify($before[$key] ?? null), $this->stringify($value)];
            }
        }

        return $changes;
    }

    private function stringify(mixed $value): mixed
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function log(DistributorDiscountSet $set, User $user, string $action, array $changes): void
    {
        DistributorDiscountSetLog::query()->create([
            'discount_set_id' => $set->id,
            'user_id' => $user->id,
            'action' => $action,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function intArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $values), fn (int $id): bool => $id > 0)));
    }

    /**
     * @return list<string>
     */
    private function stringArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => $value !== '')));
    }
}
