@extends('layouts.app')
@section('title', 'Редактирование типа документа')
@section('heading', 'Редактирование типа документа')
@section('content')
<div class="max-w-xl space-y-6"><a href="{{ route('admin.document-types.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← К списку</a>
<div class="bg-white dark:bg-gray-800 rounded-xl border p-6"><form method="POST" action="{{ route('admin.document-types.update', $documentType) }}" class="space-y-5">@csrf @method('PUT') @include('admin.document-types._form', ['documentType' => $documentType, 'contextLabels' => $contextLabels])
<div class="flex gap-3 pt-2"><button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить</button><a href="{{ route('admin.document-types.index') }}" class="px-4 py-2 border rounded-lg text-sm">Отмена</a></div></form></div></div>
@endsection
