@php
    $primaryImage = $product->primaryImage();
    $images = $product->images;
    $placeholder = asset('images/placeholder-product.svg');
@endphp

<section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5"
         x-data="{
            activeImage: @js($primaryImage?->url ?? $placeholder),
            zoomed: false,
         }">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Фотогалерея</h2>
    <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden flex items-center justify-center relative">
        <button type="button" @click="zoomed = true" class="block w-full h-full cursor-zoom-in">
            <img :src="activeImage" alt="{{ $product->name }}" class="w-full h-full object-cover" />
        </button>
    </div>
    @if($images->isNotEmpty())
        <div class="grid grid-cols-4 gap-2 mt-3">
            @foreach($images as $image)
                <button type="button" @click="activeImage = @js($image->url)"
                        class="aspect-square rounded-lg overflow-hidden border-2 transition-colors"
                        :class="activeImage === @js($image->url) ? 'border-[#c3242a]' : 'border-gray-200 dark:border-gray-600 hover:border-[#c3242a]/60'">
                    <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover" />
                </button>
            @endforeach
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">Изображения не добавлены. Показана заглушка.</p>
    @endif

    <div x-show="zoomed" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @keydown.escape.window="zoomed = false">
        <button type="button" @click="zoomed = false" class="absolute top-4 right-4 text-white text-sm bg-black/50 px-3 py-1.5 rounded-lg">Закрыть</button>
        <img :src="activeImage" alt="" class="max-w-full max-h-full object-contain" @click.outside="zoomed = false" />
    </div>
</section>
