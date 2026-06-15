<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Role;
use App\Services\Catalog\CatalogQueryService;
use App\Services\Catalog\ProductCatalogCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductLiveController extends Controller
{
    public function __invoke(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();
        $catalogRole = $user->getCurrentRole();
        $catalog = new CatalogQueryService($user);

        if (! $product->isVisibleInCatalog()) {
            return response()->json(['visible' => false, 'message' => 'Товар снят с продажи.'], 404);
        }

        if ($product->category && ! $product->category->isShownInCatalogForRole($catalogRole)) {
            return response()->json(['visible' => false, 'message' => 'Товар недоступен в каталоге.'], 404);
        }

        if (in_array($catalogRole?->slug, [Role::SLUG_MANUFACTURER], true)) {
            $profileId = $user->manufacturerProfile?->id;
            if ($profileId === null || (int) $product->manufacturer_profile_id !== (int) $profileId) {
                abort(404);
            }
        } elseif (in_array($catalogRole?->slug, [Role::SLUG_ADMIN, Role::SLUG_MANAGER, Role::SLUG_ANALYST], true)) {
            // Администратор видит любую карточку.
        } else {
            $inCatalog = $catalog->visibleProductsQuery()->where('products.id', $product->id)->exists();
            if (! $inCatalog) {
                return response()->json([
                    'visible' => false,
                    'message' => 'Товар больше недоступен в вашем регионе.',
                ], 404);
            }
        }

        $card = new ProductCatalogCardService($user, $catalog);

        return response()->json([
            'visible' => true,
            'live' => $card->livePayload($product->loadMissing([
                'manufacturerProfile.regions',
                'category.parent',
                'stocks.warehouse.region',
                'attributeValues.attribute',
            ])),
        ])->header('Cache-Control', 'no-store');
    }
}
