@php
    $nodes = $nodes ?? collect();
    $level = $level ?? 0;
    $multiple = $multiple ?? false;
@endphp

@foreach($nodes as $node)
    <button
        type="button"
        @click="select({{ $node->id }})"
        class="w-full flex items-start gap-2 py-2 pr-3 text-left text-sm transition-colors hover:bg-red-50 dark:hover:bg-red-900/20"
        :class="isSelected({{ $node->id }}) ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300'"
        style="padding-left: {{ 12 + $level * 20 }}px"
    >
        @if($multiple)
        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
            :class="isSelected({{ $node->id }}) ? 'border-[#c3242a] bg-[#c3242a]' : 'border-gray-300 dark:border-gray-500 bg-white dark:bg-gray-700'">
            <svg x-show="isSelected({{ $node->id }})" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        </span>
        @elseif($level > 0)
        <span class="w-3 shrink-0 text-gray-300 dark:text-gray-600" aria-hidden="true">└</span>
        @endif
        <span class="min-w-0 flex-1 whitespace-normal break-words {{ $level === 0 && $node->children->isNotEmpty() ? 'font-medium' : '' }}">{{ $node->name }}</span>
    </button>
    @if($node->children->isNotEmpty())
        @include('manufacturer.products._category_tree_select_nodes', [
            'nodes' => $node->children,
            'level' => $level + 1,
            'multiple' => $multiple,
        ])
    @endif
@endforeach
