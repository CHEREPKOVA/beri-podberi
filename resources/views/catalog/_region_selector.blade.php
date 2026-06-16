@php
    $catalogRegions = $catalogRegions ?? collect();
    $companyRegionId = $companyRegionId ?? null;
    $showCatalogRegionSelector = $showCatalogRegionSelector ?? false;
    $catalogRegionSetUrl = $catalogRegionSetUrl ?? '';
@endphp
@if($showCatalogRegionSelector && $catalogRegions->isNotEmpty())
<div class="px-4 pb-3 border-b border-gray-200 dark:border-gray-700">
    <label for="catalog-region-select" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Регион каталога</label>
    <select id="catalog-region-select"
        class="w-full appearance-none pl-3 pr-9 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 shadow-sm focus:ring-2 focus:ring-[#c3242a] focus:border-transparent cursor-pointer"
        @change="setCatalogRegion($event.target.value)">
        @foreach($catalogRegions as $region)
            <option value="{{ $region->id }}" @selected((int) $companyRegionId === (int) $region->id)>{{ $region->name }}</option>
        @endforeach
    </select>
</div>
@endif
