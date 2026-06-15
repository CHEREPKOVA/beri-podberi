@extends('layouts.app')
@section('title', 'Редактирование статуса претензии')
@section('heading', 'Редактирование статуса претензии')
@section('content')
<div class="max-w-xl space-y-6"><a href="{{ route('admin.claim-statuses.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← К списку</a>
<div class="bg-white dark:bg-gray-800 rounded-xl border p-6"><form method="POST" action="{{ route('admin.claim-statuses.update', $status) }}" class="space-y-5">@csrf @method('PUT') @include('admin.partials.status_record_form', ['status' => $status])
<div class="flex gap-3 pt-2"><button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить</button><a href="{{ route('admin.claim-statuses.index') }}" class="px-4 py-2 border rounded-lg text-sm">Отмена</a></div></form></div></div>
@endsection
