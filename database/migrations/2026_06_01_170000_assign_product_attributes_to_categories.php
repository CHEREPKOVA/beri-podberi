<?php

use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Перенос характеристик из «глобальных» в ветки каталога (сидер раньше ставил product_category_id = null для всех).
     */
    public function up(): void
    {
        $batteriesId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Аккумуляторы')
            ->value('id');
        $chargersId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Зарядные устройства для авто')
            ->value('id');
        $fluidsId = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', 'Масла и жидкости')
            ->value('id');

        $map = [
            'napriazenie-v' => $batteriesId,
            'emkost-ac' => $batteriesId,
            'poliarnost' => $batteriesId,
            'tip-akkumuliatora' => $batteriesId,
            'tok-xolodnoi-prokrutki-a' => $batteriesId,
            'maks-tok-zariadki-a' => $chargersId,
            'tip-masla' => $fluidsId,
            'viazkost-sae' => $fluidsId,
            'klass-api' => $fluidsId,
            'obieem-l' => $fluidsId,
            'klass-tormoznoi-zidkosti' => $fluidsId,
            'temperatura-zamerzaniia-c' => $fluidsId,
        ];

        foreach ($map as $slug => $categoryId) {
            if (! $categoryId) {
                continue;
            }

            ProductAttribute::query()
                ->whereNull('product_id')
                ->where(function ($query) use ($slug) {
                    $query->where('slug', $slug)
                        ->orWhere('slug', 'like', $slug.'-%');
                })
                ->whereNull('product_category_id')
                ->update(['product_category_id' => $categoryId]);
        }

        // Дубликаты: если есть и глобальная, и категорийная запись с одним slug — оставляем категорийную.
        $duplicateSlugs = ProductAttribute::query()
            ->whereNull('product_id')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($duplicateSlugs as $slug) {
            $scoped = ProductAttribute::query()
                ->whereNull('product_id')
                ->where('slug', $slug)
                ->whereNotNull('product_category_id')
                ->orderBy('id')
                ->get();

            if ($scoped->isEmpty()) {
                continue;
            }

            $keepId = $scoped->first()->id;

            ProductAttribute::query()
                ->whereNull('product_id')
                ->where('slug', $slug)
                ->whereNull('product_category_id')
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    public function down(): void
    {
        // Откат не восстанавливает прежнее состояние — только вручную через сидер.
    }
};
