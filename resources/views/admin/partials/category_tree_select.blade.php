@php
    $name = $name ?? 'product_category_ids';
    $multiple = $multiple ?? true;
    $tree = $categoryTree ?? collect();
    $categories = $categories ?? collect();
    $placeholder = $placeholder ?? 'Глобальное (все категории)';
    $allowClear = $allowClear ?? true;
    $clearLabel = $clearLabel ?? $placeholder;
    $inputId = $inputId ?? ('admin-category-picker-' . md5($name . ($multiple ? '-multi' : '-single')));

    if ($multiple) {
        $selectedIds = array_values(array_map('intval', old($name, $selectedIds ?? [])));
        $selectedId = null;
    } else {
        $selectedId = old($name, $selectedId ?? null);
        $selectedId = $selectedId !== null && $selectedId !== '' ? (int) $selectedId : null;
        $selectedIds = [];
    }
@endphp

@include('manufacturer.products._category_tree_select', [
    'name' => $name,
    'tree' => $tree,
    'categories' => $categories,
    'selectedId' => $selectedId,
    'selectedIds' => $selectedIds,
    'multiple' => $multiple,
    'placeholder' => $placeholder,
    'allowClear' => $allowClear,
    'clearLabel' => $clearLabel,
    'inputId' => $inputId,
    'notifyCategoryChange' => false,
])
