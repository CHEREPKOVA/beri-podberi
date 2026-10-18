<?php

namespace App\Services\Discount;

use App\Models\ManufacturerProfile;
use App\Models\ManufacturerPromoCode;
use App\Models\ManufacturerPromoCodeUsage;
use App\Models\PlatformOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManufacturerPromoCodeService
{
    /**
     * @return array<string, mixed>
     */
    public function paginate(ManufacturerProfile $profile, Request $request): array
    {
        $query = ManufacturerPromoCode::query()->forManufacturer((int) $profile->id);

        if ($request->filled('search')) {
            $query->search((string) $request->input('search'));
        }

        if ($request->filled('discount_type')) {
            $query->where('discount_type', $request->input('discount_type'));
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            $today = now()->toDateString();
            match ($status) {
                ManufacturerPromoCode::STATUS_INACTIVE => $query->where('status', ManufacturerPromoCode::STATUS_INACTIVE),
                ManufacturerPromoCode::STATUS_USED => $query->where(function ($inner): void {
                    $inner->whereColumn('uses_count', '>=', 'max_uses')
                        ->whereNotNull('max_uses');
                }),
                ManufacturerPromoCode::STATUS_EXPIRED => $query->whereNotNull('ends_at')->whereDate('ends_at', '<', $today),
                ManufacturerPromoCode::STATUS_ACTIVE => $query->where('status', ManufacturerPromoCode::STATUS_ACTIVE)
                    ->where(function ($inner) use ($today): void {
                        $inner->whereNull('ends_at')->orWhereDate('ends_at', '>=', $today);
                    })
                    ->where(function ($inner): void {
                        $inner->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
                    }),
                default => null,
            };
        }

        $codes = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();

        return ['codes' => $codes];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(ManufacturerProfile $profile, User $user, array $data): ManufacturerPromoCode
    {
        $payload = $this->normalize($data);
        $this->assertUniqueCode($payload['code']);

        return ManufacturerPromoCode::query()->create(array_merge($payload, [
            'manufacturer_profile_id' => $profile->id,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
            'uses_count' => 0,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ManufacturerPromoCode $promo, User $user, array $data): ManufacturerPromoCode
    {
        $payload = $this->normalize($data, $promo);
        if ($promo->isLocked()) {
            unset($payload['code'], $payload['discount_type'], $payload['discount_value']);
        } else {
            $this->assertUniqueCode($payload['code'], (int) $promo->id);
        }

        $promo->fill(array_merge($payload, ['updated_by_user_id' => $user->id]));
        $promo->save();

        return $promo->refresh();
    }

    public function toggle(ManufacturerPromoCode $promo, User $user): ManufacturerPromoCode
    {
        $promo->status = $promo->status === ManufacturerPromoCode::STATUS_ACTIVE
            ? ManufacturerPromoCode::STATUS_INACTIVE
            : ManufacturerPromoCode::STATUS_ACTIVE;
        $promo->updated_by_user_id = $user->id;
        $promo->save();

        return $promo;
    }

    public function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ManufacturerPromoCode::query()->where('code', $code)->exists());

        return $code;
    }

    public function recordUsage(ManufacturerPromoCode $promo, User $user, PlatformOrder $order, float $amount): void
    {
        ManufacturerPromoCodeUsage::query()->create([
            'promo_code_id' => $promo->id,
            'user_id' => $user->id,
            'platform_order_id' => $order->id,
            'discount_amount' => $amount,
            'used_at' => now(),
        ]);
        $promo->increment('uses_count');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?ManufacturerPromoCode $existing = null): array
    {
        $applyAll = (bool) ($data['apply_to_all_products'] ?? false);

        return [
            'code' => strtoupper(trim((string) ($data['code'] ?? $existing?->code ?? ''))),
            'name' => filled($data['name'] ?? null) ? trim((string) $data['name']) : null,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'discount_type' => (string) ($data['discount_type'] ?? ManufacturerPromoCode::TYPE_PERCENT),
            'discount_value' => (float) ($data['discount_value'] ?? 0),
            'status' => (string) ($data['status'] ?? ManufacturerPromoCode::STATUS_INACTIVE),
            'starts_at' => $data['starts_at'] ?? null ?: null,
            'ends_at' => $data['ends_at'] ?? null ?: null,
            'max_uses' => ($data['max_uses'] ?? '') === '' ? null : (int) $data['max_uses'],
            'per_user_limit' => (bool) ($data['per_user_limit'] ?? false) ? 1 : 0,
            'min_order_amount' => ($data['min_order_amount'] ?? '') === '' ? null : (float) $data['min_order_amount'],
            'apply_to_all_products' => $applyAll,
            'category_ids' => $applyAll ? [] : $this->intArray($data['category_ids'] ?? []),
            'product_ids' => $applyAll ? [] : $this->intArray($data['product_ids'] ?? []),
            'region_ids' => $this->intArray($data['region_ids'] ?? []),
            'company_ids' => $this->intArray($data['company_ids'] ?? []),
        ];
    }

    private function assertUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $exists = ManufacturerPromoCode::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'Промокод с таким кодом уже существует.',
            ]);
        }
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
