@php
    $categoryModel = $category ?? null;
    $roles = $roles ?? collect();
    $excludableAttributes = $excludableAttributes ?? collect();
@endphp
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm mb-2">Название</label>
        <input name="name" type="text" required value="{{ old('name', $categoryModel->name ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'name'])
    </div>
    <div>
        <label class="block text-sm mb-2">Slug (опционально)</label>
        <input name="slug" type="text" value="{{ old('slug', $categoryModel->slug ?? '') }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'slug'])
    </div>
    <div>
        <label class="block text-sm mb-2">Родительская категория</label>
        <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
            <option value="">Без родителя</option>
            @foreach($parents as $parent)
            <option value="{{ $parent->id }}" @selected((string) old('parent_id', $categoryModel->parent_id ?? '') === (string) $parent->id)>
                {{ $parent->full_path }}
            </option>
            @endforeach
        </select>
        @include('admin.partials.field-error', ['field' => 'parent_id'])
    </div>
    <div>
        <label class="block text-sm mb-2">Порядок</label>
        <input name="sort_order" type="number" min="0" value="{{ old('sort_order', $categoryModel->sort_order ?? 0) }}" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700" />
        @include('admin.partials.field-error', ['field' => 'sort_order'])
    </div>
</div>
<div class="mt-6">
    <label class="block text-sm mb-2">Описание</label>
    <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">{{ old('description', $categoryModel->description ?? '') }}</textarea>
    @include('admin.partials.field-error', ['field' => 'description'])
</div>
<div class="mt-6 space-y-3 text-sm border border-gray-200 dark:border-gray-600 rounded-xl p-4 bg-gray-50 dark:bg-gray-700/40">
    <p class="font-medium text-gray-900 dark:text-white">Каталог и поведение (ТЗ)</p>
    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $categoryModel->is_active ?? true)) /> Активна (полностью не отключена)</label>
    <label class="inline-flex items-center gap-2 mt-2 block"><input type="checkbox" name="shown_in_customer_catalog" value="1" @checked(old('shown_in_customer_catalog', $categoryModel->shown_in_customer_catalog ?? true)) /> Отображать в пользовательском каталоге и навигации</label>
    <p class="text-xs text-gray-500 ml-7 -mt-1">При снятой галочке категория скрыта в каталоге, но может использоваться во внутренних списках (товары при этом из карточек не удаляются).</p>
    <label class="inline-flex items-center gap-2 mt-2 block"><input type="checkbox" name="restrict_catalog_by_roles" value="1" @checked(old('restrict_catalog_by_roles', $categoryModel->restrict_catalog_by_roles ?? false)) /> Ограничить видимость по ролям</label>
    <div class="mt-2 ml-7 space-y-1 max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700">
        @php
            $oldRoleIds = old('catalog_role_ids', $categoryModel ? $categoryModel->catalogRoles->pluck('id')->all() : []);
        @endphp
        @foreach($roles as $role)
        <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-600 px-2 py-1 rounded">
            <input type="checkbox" name="catalog_role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, $oldRoleIds, true)) />
            <span>{{ $role->label() }}</span>
        </label>
        @endforeach
        @if($roles->isEmpty())
            <p class="text-gray-500 text-xs">Роли не найдены</p>
        @endif
    </div>
    <label class="inline-flex items-center gap-2 mt-3 block"><input type="checkbox" name="accepts_products" value="1" @checked(old('accepts_products', $categoryModel->accepts_products ?? true)) /> Допускает привязку товаров (не только контейнер подкатегорий)</label>
</div>
@if($categoryModel && $excludableAttributes->isNotEmpty())
<div class="mt-6 border border-gray-200 dark:border-gray-600 rounded-xl p-4">
    <p class="text-sm font-medium text-gray-900 dark:text-white mb-2">Отключить унаследованные свойства для этой ветки</p>
    <p class="text-xs text-gray-500 mb-3">Не применимо к характеристикам, заданным непосредственно для этой категории.</p>
    @php
        $oldExcluded = old('excluded_attribute_ids', $categoryModel->excludedAttributes->pluck('id')->all());
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto">
        @foreach($excludableAttributes as $exAttr)
        <label class="flex items-start gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="excluded_attribute_ids[]" value="{{ $exAttr->id }}" @checked(in_array($exAttr->id, $oldExcluded, true)) class="mt-0.5" />
            <span>{{ $exAttr->name }} <span class="text-gray-400">({{ $exAttr->productCategory?->name ?? 'глобальное' }})</span></span>
        </label>
        @endforeach
    </div>
</div>
@endif
@unless(isset($categoryModel))
<div class="mt-4 text-xs text-gray-500">
    Эксклюзии по свойствам доступны после сохранения: откройте редактирование категории.
</div>
@endunless
