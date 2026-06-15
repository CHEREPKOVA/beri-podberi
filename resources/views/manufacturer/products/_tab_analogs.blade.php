@if(!$product)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Сначала сохраните товар, затем сможете назначать аналоги.
    </div>
@else
    @include('shared._analog_picker', [
        'selectedAnalogs' => $selectedAnalogs ?? collect(),
        'searchUrl' => route('manufacturer.products.analogs.search', $product),
    ])
@endif
