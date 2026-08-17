@php
    $cardRole = $cardRole ?? 'end_company';
    $cartOffers = $livePayload['cart_offers'] ?? [];
@endphp

<div class="flex flex-wrap items-center gap-2">
    @if($cardRole === 'manufacturer')
        <a href="{{ route('manufacturer.products.edit', ['product' => $product, 'tab' => 'analogs']) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            Управлять аналогами
        </a>
        <a href="{{ route('manufacturer.products.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            Редактировать характеристики
        </a>
        <a href="{{ route('manufacturer.products.edit', ['product' => $product, 'tab' => 'prices']) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Управление ценами/остатками
        </a>
    @elseif($cardRole === 'admin')
        <a href="{{ route('admin.catalog.analogs.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:border-[#c3242a] hover:text-[#c3242a]">
            Аналоги
        </a>
        <a href="{{ route('admin.catalog.products.edit', $product) }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium bg-[#c3242a] text-white hover:bg-[#a01e24]">
            Редактировать карточку
        </a>
    @elseif($cardRole === 'distributor')
        @if($distributorProduct ?? null)
            <a href="{{ route('distributor.products.show', $distributorProduct) }}"
               class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                Редактировать цену и остатки
            </a>
        @endif
    @elseif($cardRole === 'end_company')
        <div x-show="live.can_add_to_order && live.cart_offers?.length" x-cloak>
            <form method="POST"
                  action="{{ route('buyer.cart.items.store') }}"
                  class="js-add-to-cart flex flex-wrap items-end gap-2"
                  data-cart-store-url="{{ route('buyer.cart.items.store') }}"
                  @if(count($cartOffers) === 1)
                      data-min-qty="{{ max(1, (int) ($cartOffers[0]['min_order_quantity'] ?? 1)) }}"
                  @endif>
                @csrf
                @if(count($cartOffers) > 1)
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Поставщик</label>
                        <select name="distributor_product_id"
                                class="js-cart-offer-select rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm min-w-[14rem] focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/20"
                                required>
                            <option value="">Выберите поставщика</option>
                            @foreach($cartOffers as $offer)
                                <option value="{{ $offer['distributor_product_id'] }}"
                                        data-min-qty="{{ max(1, (int) ($offer['min_order_quantity'] ?? 1)) }}">
                                    {{ $offer['distributor_name'] }} · {{ $offer['retail_price_formatted'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Кол-во</label>
                        <input type="text"
                               name="quantity"
                               inputmode="numeric"
                               pattern="[1-9][0-9]*"
                               value="1"
                               data-min-qty="1"
                               autocomplete="off"
                               class="js-cart-qty w-24 rounded-lg border-2 border-[#c3242a]/40 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-center font-medium focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25"
                               required>
                    </div>
                @elseif(count($cartOffers) === 1)
                    <input type="hidden" name="distributor_product_id" value="{{ $cartOffers[0]['distributor_product_id'] }}">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Кол-во</label>
                        <input type="text"
                               name="quantity"
                               inputmode="numeric"
                               pattern="[1-9][0-9]*"
                               value="{{ max(1, (int) ($cartOffers[0]['min_order_quantity'] ?? 1)) }}"
                               data-min-qty="{{ max(1, (int) ($cartOffers[0]['min_order_quantity'] ?? 1)) }}"
                               autocomplete="off"
                               class="js-cart-qty w-24 rounded-lg border-2 border-[#c3242a]/40 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-center font-medium focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25"
                               required>
                    </div>
                @endif
                @if(count($cartOffers) > 0)
                    <button type="submit"
                            class="js-add-to-cart-btn inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold bg-[#c3242a] text-white shadow-md shadow-[#c3242a]/25 hover:bg-[#a01e24] disabled:opacity-60 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                        </svg>
                        В корзину
                    </button>
                @endif
            </form>
        </div>
        <a href="{{ route('buyer.cart.index') }}"
           class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            Перейти в корзину
        </a>
    @endif
</div>
