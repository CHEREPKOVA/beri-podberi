@extends('layouts.app')

@section('title', 'Журнал действий')
@section('heading', 'Журнал действий')

@section('content')
<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4 text-sm text-gray-600 dark:text-gray-300">
        <p>Фиксация ключевых действий внутри компании: заказы, согласования изменений, правки профиля, управление сотрудниками и учётными записями.</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Кто</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действие</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Описание</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($events as $event)
                        <tr>
                            <td class="px-6 py-3.5 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $event->created_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td class="px-6 py-3.5 font-medium text-gray-900 dark:text-white">
                                {{ $event->user_name ?: ($event->user?->name ?? 'Система') }}
                            </td>
                            <td class="px-6 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                    {{ $event->actionLabel() }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-gray-700 dark:text-gray-300">
                                {{ $event->description }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">Записей пока нет.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
