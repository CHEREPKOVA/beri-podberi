@php
    $node = $node ?? null;
    $level = $level ?? 0;
    $routeName = $routeName ?? 'manufacturer.catalog.index';
    $ancestorSlugs = $ancestorSlugs ?? [];
@endphp
@if($node)
<div class="mt-0.5">
    <div class="flex items-center rounded-lg" style="padding-left: {{ $level * 12 + 4 }}px;">
        @if($node->children && $node->children->isNotEmpty())
        <button type="button" @click="toggleCategoryOpen('{{ $node->slug }}')" class="p-1 -ml-1 rounded text-gray-500 hover:text-gray-700 dark:hover:text-gray-400"
            :aria-label="isCategoryOpen('{{ $node->slug }}') ? 'Свернуть' : 'Развернуть'">
            <svg class="w-4 h-4 transition-transform" :class="isCategoryOpen('{{ $node->slug }}') ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
        </button>
        @else
        <span class="w-4 h-4 inline-block"></span>
        @endif
        <a href="{{ route($routeName, ['category' => $node->slug]) }}"
            data-category-slug="{{ $node->slug }}"
            data-ancestor-slugs="{{ implode(',', $ancestorSlugs) }}"
            data-has-children="{{ ($node->children && $node->children->isNotEmpty()) ? '1' : '0' }}"
            @click.prevent="loadCategory('{{ addslashes($node->slug) }}')"
            :class="categoryNodeLinkClass('{{ $node->slug }}')">
            {{ $node->name }}
        </a>
    </div>
    @if($node->children && $node->children->isNotEmpty())
    <div x-show="isCategoryOpen('{{ $node->slug }}')" x-transition class="border-l border-gray-200 dark:border-gray-600 ml-2" style="margin-left: {{ $level * 12 + 10 }}px;">
        @foreach($node->children as $child)
            @include('manufacturer.catalog._tree_node', [
                'node' => $child,
                'level' => $level + 1,
                'ancestorSlugs' => array_merge($ancestorSlugs, [$node->slug]),
                'routeName' => $routeName,
            ])
        @endforeach
    </div>
    @endif
</div>
@endif
