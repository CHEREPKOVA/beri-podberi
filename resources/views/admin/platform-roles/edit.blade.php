@extends('layouts.app')
@section('title', 'Редактирование роли')
@section('heading', 'Редактирование роли')
@section('content')
<div class="max-w-xl space-y-6">
    <a href="{{ route('admin.platform-roles.index') }}" class="text-sm text-gray-500 hover:text-[#c3242a]">← К списку</a>
    <div class="bg-white dark:bg-gray-800 rounded-xl border p-6">
        <form method="POST" action="{{ route('admin.platform-roles.update', $role) }}" class="space-y-5">@csrf @method('PUT')
            @include('admin.platform-roles._form', ['role' => $role, 'slugLocked' => in_array($role->slug, \App\Models\Role::protectedSlugs(), true)])
            <div class="flex gap-3 pt-2"><button type="submit" class="px-4 py-2 bg-[#c3242a] text-white rounded-lg text-sm">Сохранить</button><a href="{{ route('admin.platform-roles.index') }}" class="px-4 py-2 border rounded-lg text-sm">Отмена</a></div>
        </form>
    </div>
</div>
@endsection
