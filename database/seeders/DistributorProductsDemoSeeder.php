<?php

namespace Database\Seeders;

use App\Models\DistributorProduct;
use App\Models\DistributorProductStock;
use App\Models\DistributorProfile;
use App\Models\DistributorWarehouse;
use App\Models\Product;
use App\Models\User;
use App\Services\DistributorProductLogger;
use Illuminate\Database\Seeder;

class DistributorProductsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $manufacturerProducts = Product::query()
            ->with(['manufacturerProfile', 'category', 'stocks'])
            ->where('status', Product::STATUS_ACTIVE)
            ->limit(30)
            ->get();

        if ($manufacturerProducts->isEmpty()) {
            $this->command->warn('DistributorProductsDemoSeeder: нет товаров производителя.');

            return;
        }

        $distributorUsers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('slug', 'distributor'))
            ->get();

        foreach ($distributorUsers as $user) {
            $profile = $user->distributorProfile ?? $user->getOrCreateDistributorProfile();

            if (! $profile->warehouses()->exists()) {
                DistributorWarehouse::create([
                    'distributor_profile_id' => $profile->id,
                    'name' => 'Основной склад',
                    'address' => 'г. Москва, складская зона 1',
                    'type' => DistributorWarehouse::TYPE_MAIN,
                    'is_active' => true,
                ]);
            }

            $warehouses = $profile->warehouses()->get();
            $idx = 0;

            foreach ($manufacturerProducts->take(15) as $source) {
                $internalSku = 'D-'.$profile->id.'-'.($source->sku ?: $source->id);

                $distProduct = DistributorProduct::updateOrCreate(
                    [
                        'distributor_profile_id' => $profile->id,
                        'internal_sku' => $internalSku,
                    ],
                    [
                        'source_product_id' => $source->id,
                        'manufacturer_profile_id' => $source->manufacturer_profile_id,
                        'product_category_id' => $source->category_id,
                        'name' => $source->name,
                        'manufacturer_sku' => $source->manufacturer_sku ?: $source->sku,
                        'brand' => $source->manufacturerProfile?->short_name ?: $source->manufacturerProfile?->displayName(),
                        'barcode' => $source->barcode ?: $source->ean,
                        'short_description' => \Illuminate\Support\Str::limit(strip_tags((string) $source->description), 200),
                        'description' => $source->description,
                        'purchase_price' => $source->base_price ? round((float) $source->base_price * 0.75, 2) : null,
                        'retail_price' => $source->base_price ? round((float) $source->base_price * 1.15, 2) : null,
                        'price_updated_at' => now(),
                        'status' => $idx % 5 === 0
                            ? DistributorProduct::STATUS_HIDDEN
                            : ($idx % 11 === 0 ? DistributorProduct::STATUS_ARCHIVE : DistributorProduct::STATUS_ACTIVE),
                        'sync_source' => $idx % 7 === 0 ? DistributorProduct::SYNC_1C : DistributorProduct::SYNC_MANUFACTURER,
                        'synced_at' => $idx % 7 === 0 ? now()->subHours(2) : null,
                        'managed_by_1c' => $idx % 7 === 0,
                        'manufacturer_archived' => $idx % 13 === 0,
                        'min_order_quantity' => $source->min_order_quantity ?: 1,
                    ],
                );

                foreach ($warehouses as $wh) {
                    $qty = max(0, (int) ($source->stocks->sum('quantity') / max(1, $warehouses->count())) - ($idx * 2));
                    DistributorProductStock::updateOrCreate(
                        [
                            'distributor_product_id' => $distProduct->id,
                            'distributor_warehouse_id' => $wh->id,
                        ],
                        [
                            'quantity' => $qty,
                            'stock_updated_at' => now(),
                        ],
                    );
                }

                if ($distProduct->wasRecentlyCreated) {
                    DistributorProductLogger::log(
                        $distProduct,
                        'created',
                        'Позиция добавлена в номенклатуру (демо)',
                    );
                }

                $idx++;
            }
        }

        $this->command->info('DistributorProductsDemoSeeder: номенклатура дистрибьюторов заполнена.');
    }
}
