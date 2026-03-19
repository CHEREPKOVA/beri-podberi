@php
    $node = $node ?? null;
    $level = $level ?? 0;
    $selectedId = $selectedId ?? null;
    $selectedSlug = $selectedSlug ?? null;
@endphp
@if($node)
<div x-data="{ open: {{ ($selectedId && $selectedId == $node->id) || ($selectedSlug && $selectedSlug === $node->slug) || ($node->children && $node->children->isNotEmpty()) ? 'true' : 'false' }} }" class="mt-0.5">
    <div class="flex items-center rounded-lg" style="padding-left: {{ $level * 12 + 4 }}px;">
        @if($node->children && $node->children->isNotEmpty())
        <button type="button" @click="open = !open" class="p-1 -ml-1 rounded text-gray-500 hover:text-gray-700 dark:hover:text-gray-400"
            :aria-label="open ? 'Свернуть' : 'Развернуть'">
            <svg class="w-4 h-4 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        @else
        <span class="w-4 h-4 inline-block"></span>
        @endif
        <a href="{{ route('manufacturer.catalog.index', ['category' => $node->slug]) }}"
            @click.prevent="$dispatch('load-category', { slug: '{{ addslashes($node->slug) }}' })"
            class="flex-1 px-2 py-1.5 rounded-lg text-sm transition-colors {{ ($selectedId && $selectedId == $node->id) || ($selectedSlug && $selectedSlug === $node->slug) ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
            {{ $node->name }}
        </a>
    </div>
    @if($node->children && $node->children->isNotEmpty())
    <div x-show="open" x-transition class="border-l border-gray-200 dark:border-gray-600 ml-2" style="margin-left: {{ $level * 12 + 10 }}px;">
        @foreach($node->children as $child)
            @include('manufacturer.catalog._tree_node', ['node' => $child, 'level' => $level + 1, 'selectedId' => $selectedId, 'selectedSlug' => $selectedSlug])
        @endforeach
    </div>
    @endif
</div>
@endif
