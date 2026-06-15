<div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
    <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6" @click.stop>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Удаление</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6" x-text="deleteMessage"></p>
        <form :action="deleteFormAction" method="POST" class="flex justify-end gap-3">
            @csrf
            @method('DELETE')
            <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-gray-600">Отмена</button>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">Удалить</button>
        </form>
    </div>
</div>
