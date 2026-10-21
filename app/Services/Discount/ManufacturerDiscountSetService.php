<?php

namespace App\Services\Discount;

use App\Models\ManufacturerDiscountSet;
use App\Models\ManufacturerDiscountSetLog;
use App\Models\ManufacturerDistributorPartnership;
use App\Models\ManufacturerProfile;
use App\Models\PlatformOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManufacturerDiscountSetService
{
    /**
     * @return array<string, mixed>
     */
    public function paginate(ManufacturerProfile $profile, Request $request): array
    {
        $this->syncExpired($profile);

        $query = ManufacturerDiscountSet::query()
            ->forManufacturer((int) $profile->id);

        if ($request->filled('search')) {
            $query->search((string) $request->input('search'));
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === ManufacturerDiscountSet::STATUS_COMPLETED) {
                $query->where(function ($inner): void {
                    $inner->where('status', ManufacturerDiscountSet::STATUS_COMPLETED)
                        ->orWhere(function ($expired): void {
                            $expired->where('status', ManufacturerDiscountSet::STATUS_ACTIVE)
                                ->where('is_indefinite', false)
                                ->whereNotNull('ends_at')
                                ->whereDate('ends_at', '<', now()->toDateString());
                        });
                });
            } elseif ($status === ManufacturerDiscountSet::STATUS_ACTIVE) {
                $query->where('status', ManufacturerDiscountSet::STATUS_ACTIVE)
                    ->where(function ($inner): void {
                        $inner->where('is_indefinite', true)
                            ->orWhereNull('ends_at')
                            ->orWhereDate('ends_at', '>=', now()->toDateString());
                    });
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $query->where(function ($inner) use ($categoryId): void {
                $inner->where('apply_to_all_products', true)
                    ->orWhereJsonContains('category_ids', $categoryId)
                    ->orWhereJsonContains('category_ids', (string) $categoryId);
            });
        }

        if ($request->filled('distributor_id')) {
            $distributorId = (int) $request->input('distributor_id');
            $query->where(function ($inner) use ($distributorId): void {
                $inner->where('audience_type', ManufacturerDiscountSet::AUDIENCE_ALL)
                    ->orWhereJsonContains('distributor_ids', $distributorId)
                    ->orWhereJsonContains('distributor_ids', (string) $distributorId);
            });
        }

        $sets = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        $distributorNames = $this->distributorMap($profile);

        return [
            'sets' => $sets,
            'rows' => $sets->getCollection()->map(fn (ManufacturerDiscountSet $set) => $this->listRow($set, $distributorNames)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(ManufacturerProfile $profile, User $user, array $data): ManufacturerDiscountSet
    {
        $payload = $this->normalizePayload($profile, $data);

        return DB::transaction(function () use ($profile, $user, $payload): ManufacturerDiscountSet {
            $set = ManufacturerDiscountSet::query()->create(array_merge($payload, [
                'manufacturer_profile_id' => $profile->id,
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
    public function update(ManufacturerDiscountSet $set, User $user, array $data): ManufacturerDiscountSet
    {
        $payload = $this->normalizePayload($set->manufacturerProfile, $data);

        $before = $this->snapshot($set);

        return DB::transaction(function () use ($set, $user, $payload, $before): ManufacturerDiscountSet {
            $set->fill(array_merge($payload, ['updated_by_user_id' => $user->id]));
            $set->save();
            $changes = $this->diff($before, $this->snapshot($set));
            if ($changes !== []) {
                $this->log($set, $user, 'updated', $changes);
            }

            return $set->refresh();
        });
    }

    public function toggle(ManufacturerDiscountSet $set, User $user): ManufacturerDiscountSet
    {
        $next = $set->effectiveStatus() === ManufacturerDiscountSet::STATUS_ACTIVE
            ? ManufacturerDiscountSet::STATUS_INACTIVE
            : ManufacturerDiscountSet::STATUS_ACTIVE;

        $set->status = $next;
        $set->updated_by_user_id = $user->id;
        $set->save();
        $this->log($set, $user, $next === ManufacturerDiscountSet::STATUS_ACTIVE ? 'activated' : 'deactivated', [
            'status' => [
                $next === ManufacturerDiscountSet::STATUS_ACTIVE ? ManufacturerDiscountSet::STATUS_INACTIVE : ManufacturerDiscountSet::STATUS_ACTIVE,
                $next,
            ],
        ]);

        return $set;
    }

    public function duplicate(ManufacturerDiscountSet $set, User $user): ManufacturerDiscountSet
    {
        $copy = $set->replicate([
            'created_by_user_id',
            'updated_by_user_id',
        ]);
        $copy->name = $set->name.' (копия)';
        $copy->status = ManufacturerDiscountSet::STATUS_INACTIVE;
        $copy->created_by_user_id = $user->id;
        $copy->updated_by_user_id = $user->id;
        $copy->save();
        $this->log($copy, $user, 'copied', [
            'source_id' => [$set->id, $copy->id],
            'name' => [$set->name, $copy->name],
        ]);

        return $copy;
    }

    public function delete(ManufacturerDiscountSet $set, User $user): void
    {
        if ($this->isUsedInActiveOrders($set)) {
            throw new InvalidArgumentException('Нельзя удалить набор, который используется в активных заказах.');
        }

        $this->log($set, $user, 'deleted', ['name' => [$set->name, null]]);
        $set->delete();
    }

    /**
     * @param  list<int>  $ids
     * @return array{disabled:int, deleted:int, skipped:int}
     */
    public function bulk(ManufacturerProfile $profile, User $user, array $ids, string $action): array
    {
        $sets = ManufacturerDiscountSet::query()
            ->forManufacturer((int) $profile->id)
            ->whereIn('id', $ids)
            ->get();

        $disabled = 0;
        $deleted = 0;
        $skipped = 0;

        foreach ($sets as $set) {
            if ($action === 'disable') {
                if ($set->status !== ManufacturerDiscountSet::STATUS_INACTIVE) {
                    $this->toggleTo($set, $user, ManufacturerDiscountSet::STATUS_INACTIVE);
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

    public function export(ManufacturerProfile $profile): StreamedResponse
    {
        $sets = ManufacturerDiscountSet::query()
            ->forManufacturer((int) $profile->id)
            ->orderBy('name')
            ->get();

        $filename = 'discount-sets-'.now()->format('Ymd-His').'.xls';

        return response()->streamDownload(function () use ($sets): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, [
                'Название', 'Тип', 'Размер скидки', 'Применимо к', 'Статус',
                'Дата создания', 'Дата изменения', 'Дистрибьюторы',
            ], ';');
            foreach ($sets as $set) {
                fputcsv($file, [
                    $set->name,
                    $set->typeLabel(),
                    $set->sizeLabel(),
                    $set->appliesLabel(),
                    $set->statusLabel(),
                    $set->created_at?->format('d.m.Y H:i'),
                    $set->updated_at?->format('d.m.Y H:i'),
                    count($set->intList($set->distributor_ids)),
                ], ';');
            }
            fclose($file);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @return list<array{id:int,name:string,status:string,region:?string}>
     */
    public function partnerDistributors(ManufacturerProfile $profile): array
    {
        return ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $profile->id)
            ->with(['distributorProfile.regions'])
            ->orderByDesc('id')
            ->get()
            ->map(function (ManufacturerDistributorPartnership $partnership): array {
                $distributor = $partnership->distributorProfile;

                return [
                    'id' => (int) $distributor?->id,
                    'name' => $distributor?->displayName() ?: 'Дистрибьютор #'.$partnership->distributor_profile_id,
                    'status' => $partnership->status,
                    'region' => $distributor?->primaryRegion()?->name,
                    'legal_form' => $distributor?->legal_form,
                ];
            })
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function productOptions(ManufacturerProfile $profile, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Product::query()
            ->forManufacturer((int) $profile->id)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn (Product $product) => [
                $product->id => trim($product->name.($product->sku ? ' ('.$product->sku.')' : '')),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public function searchProducts(ManufacturerProfile $profile, string $term, int $limit = 20): array
    {
        $term = trim($term);
        $query = Product::query()->forManufacturer((int) $profile->id)->orderBy('name');
        if ($term !== '') {
            $query->search($term);
        }

        return $query->limit($limit)
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'label' => trim($product->name.($product->sku ? ' · '.$product->sku : '')),
            ])
            ->all();
    }

    /**
     * @return list<ManufacturerDiscountSet>
     */
    public function findConflicts(ManufacturerProfile $profile, array $payload, ?int $ignoreId = null): array
    {
        if (($payload['status'] ?? null) !== ManufacturerDiscountSet::STATUS_ACTIVE) {
            return [];
        }

        $candidates = ManufacturerDiscountSet::query()
            ->forManufacturer((int) $profile->id)
            ->where('status', ManufacturerDiscountSet::STATUS_ACTIVE)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->filter(fn (ManufacturerDiscountSet $set): bool => $set->isCurrentlyApplicable());

        $newDistributors = $this->resolveAudienceDistributorIds($profile, $payload);
        $newProducts = $this->resolveCoveredSignature($payload);

        return $candidates->filter(function (ManufacturerDiscountSet $set) use ($newDistributors, $newProducts): bool {
            $existingDistributors = $this->resolveAudienceDistributorIds($set->manufacturerProfile, [
                'audience_type' => $set->audience_type,
                'distributor_ids' => $set->distributor_ids,
                'audience_region_ids' => $set->audience_region_ids,
                'region_ids' => $set->region_ids,
                'company_types' => $set->company_types,
            ]);
            if ($newDistributors !== null && $existingDistributors !== null && array_intersect($newDistributors, $existingDistributors) === []) {
                return false;
            }

            $existingProducts = $this->resolveCoveredSignature([
                'apply_to_all_products' => $set->apply_to_all_products,
                'product_ids' => $set->product_ids,
                'category_ids' => $set->category_ids,
            ]);

            if ($newProducts['all'] || $existingProducts['all']) {
                return true;
            }

            if (array_intersect($newProducts['products'], $existingProducts['products']) !== []) {
                return true;
            }

            return array_intersect($newProducts['categories'], $existingProducts['categories']) !== [];
        })->values()->all();
    }

    public function isUsedInActiveOrders(ManufacturerDiscountSet $set): bool
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
     * Применяет текущие значения формы к копии набора без сохранения в БД (для проверки скидки).
     *
     * @param  array<string, mixed>  $data
     */
    public function draftForPreview(ManufacturerDiscountSet $set, array $data): ManufacturerDiscountSet
    {
        $draft = $set->replicate();
        $draft->id = $set->id;
        $draft->manufacturer_profile_id = $set->manufacturer_profile_id;
        $draft->exists = true;

        $hasDraft = array_key_exists('audience_type', $data) || array_key_exists('type', $data);
        if ($hasDraft) {
            // Неотмеченные чекбоксы не приходят в запросе — для черновика формы это false.
            foreach ([
                'require_all_conditions',
                'apply_to_all_products',
                'apply_only_active_products',
                'only_new_distributors',
                'is_indefinite',
                'auto_deactivate',
            ] as $checkbox) {
                if (! array_key_exists($checkbox, $data)) {
                    $data[$checkbox] = false;
                }
            }

            // Очищенные multi-select тоже не приходят — иначе остаются старые значения из БД.
            foreach ([
                'category_ids',
                'excluded_category_ids',
                'product_ids',
                'region_ids',
                'audience_region_ids',
                'distributor_ids',
                'company_types',
                'tiers',
            ] as $listField) {
                if (! array_key_exists($listField, $data)) {
                    $data[$listField] = [];
                }
            }
        }

        $payload = $this->normalizePayload($set->manufacturerProfile, array_merge([
            'name' => $set->name,
            'type' => $set->type,
            'status' => $set->status,
            'audience_type' => $set->audience_type,
            'is_indefinite' => $set->is_indefinite,
            'apply_to_all_products' => $set->apply_to_all_products,
            'apply_only_active_products' => $set->apply_only_active_products,
            'only_new_distributors' => $set->only_new_distributors,
            'require_all_conditions' => $set->require_all_conditions,
            'auto_deactivate' => $set->auto_deactivate,
            'discount_percent' => $set->discount_percent,
            'discount_amount' => $set->discount_amount,
            'min_order_amount' => $set->min_order_amount,
            'min_quantity' => $set->min_quantity,
            'category_ids' => $set->category_ids,
            'excluded_category_ids' => $set->excluded_category_ids,
            'product_ids' => $set->product_ids,
            'region_ids' => $set->region_ids,
            'audience_region_ids' => $set->audience_region_ids,
            'distributor_ids' => $set->distributor_ids,
            'company_types' => $set->company_types,
            'tiers' => $set->tiers,
            'starts_at' => $set->starts_at?->toDateString(),
            'ends_at' => $set->ends_at?->toDateString(),
            'description' => $set->description,
        ], $data));

        // Для проверки считаем набор активным, даже если в форме ещё «отключен».
        $payload['status'] = ManufacturerDiscountSet::STATUS_ACTIVE;
        $draft->fill($payload);

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizePayload(ManufacturerProfile $profile, array $data): array
    {
        $type = (string) ($data['type'] ?? ManufacturerDiscountSet::TYPE_PERCENT);
        $isIndefinite = $this->toBool($data['is_indefinite'] ?? false);
        $applyAll = $this->toBool($data['apply_to_all_products'] ?? false);
        $audience = (string) ($data['audience_type'] ?? ManufacturerDiscountSet::AUDIENCE_ALL);
        if ($type === ManufacturerDiscountSet::TYPE_INDIVIDUAL) {
            $audience = ManufacturerDiscountSet::AUDIENCE_SELECTED;
        }

        $startsAt = $data['starts_at'] ?? null;
        $endsAt = $isIndefinite ? null : ($data['ends_at'] ?? null);

        $tiers = [];
        foreach ($data['tiers'] ?? [] as $tier) {
            if (! is_array($tier)) {
                continue;
            }
            if (($tier['min_amount'] ?? '') === '' && ($tier['min_quantity'] ?? '') === '') {
                continue;
            }
            $tiers[] = [
                'min_amount' => ($tier['min_amount'] ?? '') === '' ? null : (float) $tier['min_amount'],
                'min_quantity' => ($tier['min_quantity'] ?? '') === '' ? null : (int) $tier['min_quantity'],
                'percent' => ($tier['percent'] ?? '') === '' ? null : (float) $tier['percent'],
                'amount' => ($tier['amount'] ?? '') === '' ? null : (float) $tier['amount'],
            ];
        }

        return [
            'name' => trim((string) $data['name']),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'type' => $type,
            'status' => (string) ($data['status'] ?? ManufacturerDiscountSet::STATUS_INACTIVE),
            'discount_percent' => ($data['discount_percent'] ?? '') === '' ? null : (float) $data['discount_percent'],
            'discount_amount' => ($data['discount_amount'] ?? '') === '' ? null : (float) $data['discount_amount'],
            'min_order_amount' => ($data['min_order_amount'] ?? '') === '' ? null : (float) $data['min_order_amount'],
            'min_quantity' => ($data['min_quantity'] ?? '') === '' ? null : (int) $data['min_quantity'],
            'require_all_conditions' => $this->toBool($data['require_all_conditions'] ?? false),
            'apply_to_all_products' => $type === ManufacturerDiscountSet::TYPE_CATEGORY ? false : $applyAll,
            'apply_only_active_products' => $this->toBool($data['apply_only_active_products'] ?? false),
            'only_new_distributors' => $this->toBool($data['only_new_distributors'] ?? false),
            'audience_type' => $audience,
            'category_ids' => ($applyAll && $type !== ManufacturerDiscountSet::TYPE_CATEGORY)
                ? []
                : $this->intArray($data['category_ids'] ?? []),
            'excluded_category_ids' => $type === ManufacturerDiscountSet::TYPE_CATEGORY
                ? []
                : $this->intArray($data['excluded_category_ids'] ?? []),
            'product_ids' => ($applyAll || $type === ManufacturerDiscountSet::TYPE_CATEGORY)
                ? []
                : $this->intArray($data['product_ids'] ?? []),
            'region_ids' => $this->intArray($data['region_ids'] ?? []),
            'audience_region_ids' => $this->intArray($data['audience_region_ids'] ?? []),
            'distributor_ids' => $audience === ManufacturerDiscountSet::AUDIENCE_SELECTED
                ? ($type === ManufacturerDiscountSet::TYPE_INDIVIDUAL
                    ? array_slice($this->intArray($data['distributor_ids'] ?? []), 0, 1)
                    : $this->intArray($data['distributor_ids'] ?? []))
                : [],
            'company_types' => $audience === ManufacturerDiscountSet::AUDIENCE_COMPANY_TYPES
                ? array_values(array_filter(array_map('strval', $data['company_types'] ?? [])))
                : [],
            'tiers' => $type === ManufacturerDiscountSet::TYPE_VOLUME_TIER ? $tiers : [],
            'starts_at' => $startsAt ?: null,
            'ends_at' => $endsAt ?: null,
            'is_indefinite' => $isIndefinite || ! $endsAt,
            'auto_deactivate' => $this->toBool($data['auto_deactivate'] ?? ($type === ManufacturerDiscountSet::TYPE_PROMO)),
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    public function formCatalog(ManufacturerProfile $profile, ?ManufacturerDiscountSet $set = null): array
    {
        $categoryTree = ProductCategory::assignableTree();
        $categories = ProductCategory::query()
            ->active()
            ->assignableForProducts()
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'types' => config('manufacturer_discounts.types'),
            'audienceTypes' => config('manufacturer_discounts.audience_types'),
            'companyTypes' => config('manufacturer_discounts.company_types'),
            'categoryTree' => $categoryTree,
            'categories' => $categories,
            'categoryOptions' => ProductCategory::selectOptionsFromTree($categoryTree),
            'regionOptions' => Region::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'distributors' => $this->partnerDistributors($profile),
            'selectedProducts' => $this->productOptions($profile, $set?->intList($set->product_ids) ?? []),
        ];
    }

    private function syncExpired(ManufacturerProfile $profile): void
    {
        ManufacturerDiscountSet::query()
            ->forManufacturer((int) $profile->id)
            ->where('status', ManufacturerDiscountSet::STATUS_ACTIVE)
            ->where('is_indefinite', false)
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->update(['status' => ManufacturerDiscountSet::STATUS_INACTIVE]);
    }

    /**
     * @param  array<int, string>  $distributorNames
     * @return array<string, mixed>
     */
    private function listRow(ManufacturerDiscountSet $set, array $distributorNames): array
    {
        $ids = $set->intList($set->distributor_ids);
        $names = array_values(array_filter(array_map(fn (int $id) => $distributorNames[$id] ?? null, $ids)));

        return [
            'set' => $set,
            'distributor_count' => $set->audience_type === ManufacturerDiscountSet::AUDIENCE_ALL
                ? count($distributorNames)
                : count($ids),
            'distributor_names' => $names,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function distributorMap(ManufacturerProfile $profile): array
    {
        return ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $profile->id)
            ->with('distributorProfile')
            ->get()
            ->mapWithKeys(function (ManufacturerDistributorPartnership $row) {
                $id = (int) $row->distributor_profile_id;

                return [$id => $row->distributorProfile?->displayName() ?: '#'.$id];
            })
            ->all();
    }

    /**
     * @param  list<ManufacturerDiscountSet>  $conflicts
     */
    public function conflictMessage(array $conflicts): string
    {
        $names = collect($conflicts)->pluck('name')->filter()->take(3)->implode(', ');

        $message = 'У выбранного дистрибьютора уже есть набор скидок, перекрывающий этот. Продолжить?';
        if ($names !== '') {
            $message .= ' Пересечение: '.$names.'.';
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<int>|null
     */
    private function resolveAudienceDistributorIds(ManufacturerProfile $profile, array $payload): ?array
    {
        $partners = ManufacturerDistributorPartnership::query()
            ->where('manufacturer_profile_id', $profile->id)
            ->where('status', ManufacturerDistributorPartnership::STATUS_ACTIVE)
            ->pluck('distributor_profile_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return match ($payload['audience_type'] ?? ManufacturerDiscountSet::AUDIENCE_ALL) {
            ManufacturerDiscountSet::AUDIENCE_SELECTED => $this->intArray($payload['distributor_ids'] ?? []),
            ManufacturerDiscountSet::AUDIENCE_ALL => $partners,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{all:bool,products:list<int>,categories:list<int>}
     */
    private function resolveCoveredSignature(array $payload): array
    {
        $all = (bool) ($payload['apply_to_all_products'] ?? false);
        $products = $this->intArray($payload['product_ids'] ?? []);
        $categories = $this->intArray($payload['category_ids'] ?? []);

        return [
            'all' => $all && $products === [] && $categories === [],
            'products' => $products,
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ManufacturerDiscountSet $set): array
    {
        return [
            'name' => $set->name,
            'description' => $set->description,
            'type' => $set->type,
            'status' => $set->status,
            'discount_percent' => $set->discount_percent,
            'discount_amount' => $set->discount_amount,
            'min_order_amount' => $set->min_order_amount,
            'min_quantity' => $set->min_quantity,
            'require_all_conditions' => $set->require_all_conditions,
            'apply_to_all_products' => $set->apply_to_all_products,
            'apply_only_active_products' => $set->apply_only_active_products,
            'only_new_distributors' => $set->only_new_distributors,
            'audience_type' => $set->audience_type,
            'category_ids' => $set->intList($set->category_ids),
            'excluded_category_ids' => $set->intList($set->excluded_category_ids),
            'product_ids' => $set->intList($set->product_ids),
            'region_ids' => $set->intList($set->region_ids),
            'audience_region_ids' => $set->intList($set->audience_region_ids),
            'distributor_ids' => $set->intList($set->distributor_ids),
            'company_types' => array_values(array_map('strval', is_array($set->company_types) ? $set->company_types : [])),
            'tiers' => $set->normalizedTiers(),
            'starts_at' => $set->starts_at?->toDateString(),
            'ends_at' => $set->ends_at?->toDateString(),
            'is_indefinite' => $set->is_indefinite,
            'auto_deactivate' => $set->auto_deactivate,
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
                $changes[$key] = [$before[$key] ?? null, $value];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function log(ManufacturerDiscountSet $set, User $user, string $action, array $changes): void
    {
        ManufacturerDiscountSetLog::query()->create([
            'discount_set_id' => $set->id,
            'user_id' => $user->id,
            'action' => $action,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }

    private function toggleTo(ManufacturerDiscountSet $set, User $user, string $status): void
    {
        $from = $set->status;
        $set->status = $status;
        $set->updated_by_user_id = $user->id;
        $set->save();
        $this->log($set, $user, $status === ManufacturerDiscountSet::STATUS_ACTIVE ? 'activated' : 'deactivated', [
            'status' => [$from, $status],
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
}
