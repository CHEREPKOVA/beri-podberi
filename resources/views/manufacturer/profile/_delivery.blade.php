<div>
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Доставка и логистика</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Настройте способы доставки и транспортные компании</p>
    </div>

    <form method="POST" action="{{ route('manufacturer.profile.delivery.update') }}">
        @csrf
        @method('PUT')

        <div class="space-y-8">
            {{-- Способы доставки --}}
            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Доступные способы доставки</h3>
                <div class="space-y-3">
                    @foreach($deliveryMethods as $method)
                    <label class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20 transition">
                        <input
                            type="checkbox"
                            name="delivery_methods[]"
                            value="{{ $method->id }}"
                            {{ $profile->deliveryMethods->contains($method->id) ? 'checked' : '' }}
                            class="mt-0.5 h-5 w-5 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"
                        >
                        <div class="flex-1">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $method->name }}</div>
                            @if($method->description)
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $method->description }}</div>
                            @endif
                            @if($method->requires_tracking)
                            <div class="inline-flex items-center gap-1.5 mt-2 text-xs font-medium text-[#c3242a] bg-red-50 dark:bg-red-900/30 px-2 py-1 rounded">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Требуется ввод трек-номера при создании заказа
                            </div>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Транспортные компании --}}
            <div>
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Транспортные компании</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Выберите транспортные компании, с которыми вы работаете</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($transportCompanies as $company)
                    <label class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer has-[:checked]:border-[#c3242a] has-[:checked]:bg-red-50 dark:has-[:checked]:bg-red-900/20 transition">
                        <input
                            type="checkbox"
                            name="transport_companies[]"
                            value="{{ $company->id }}"
                            {{ $profile->transportCompanies->contains($company->id) ? 'checked' : '' }}
                            class="h-5 w-5 rounded border-gray-300 text-[#c3242a] focus:ring-[#c3242a]"
                        >
                        <span class="flex-1 text-sm font-medium text-gray-900 dark:text-white">{{ $company->name }}</span>
                        @if($company->website)
                        <a href="{{ $company->website }}" target="_blank" class="text-gray-400 hover:text-[#c3242a] transition" title="Перейти на сайт" @click.stop>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        @endif
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Особые условия доставки --}}
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Особые условия доставки</label>
                <textarea
                    name="delivery_notes"
                    rows="4"
                    class="shadow-theme-xs focus:border-[#c3242a] focus:ring-[#c3242a]/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 resize-none"
                    placeholder="Укажите особые условия доставки, минимальные партии, сроки и т.д."
                    maxlength="1000"
                >{{ old('delivery_notes', $profile->delivery_notes) }}</textarea>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">До 1000 символов. Эта информация будет отображаться при оформлении заказа.</p>
            </div>
        </div>

        {{-- Кнопка сохранения --}}
        <div class="mt-8">
            <button
                type="submit"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c3242a] text-white text-sm font-medium rounded-lg hover:bg-[#a01e24] transition shadow-theme-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Сохранить настройки
            </button>
        </div>
    </form>
</div>
