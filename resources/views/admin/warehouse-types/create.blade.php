@extends('layouts.app')
@section('title', 'Новый тип склада')
@section('heading', 'Новый тип склада')
@section('content')
<div class="max-w-xl space-y-6"><a href="{{ route('admin.warehouse-types.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← К списку</a>
<div class="bg-white dark:bg-gray-800 rounded-xl border p-6"><form method="POST" action="{{ route('admin.warehouse-types.store') }}" class="space-y-5">@csrf @include('admin.warehouse-types._form', ['appliesToLabels' => $appliesToLabels])
<div class="flex gap-3 pt-2"><button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить</button><a href="{{ route('admin.warehouse-types.index') }}" class="px-4 py-2 border rounded-lg text-sm">Отмена</a></div></form></div></div>
@endsection
