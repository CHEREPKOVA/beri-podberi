<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: { extend: {} },
            darkMode: 'class'
        }
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700" rel="stylesheet" />
    <style type="text/tailwindcss">
        body { font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif; }
        .shadow-theme-xs { box-shadow: 0px 1px 2px 0px rgba(16, 24, 40, 0.05); }
        [x-cloak] { display: none !important; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200" x-data="{ sidebarOpen: true }">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="fixed left-0 top-0 z-40 h-screen bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transition-all duration-300">
            <div class="flex h-16 items-center justify-between px-4 border-b border-gray-200 dark:border-gray-700">
                <a href="{{ url('/dashboard') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/logo-beri.jpg') }}" alt="Бери-Подбери" class="h-8 w-auto object-contain" />
                </a>
                <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
            <nav class="mt-4 px-3 space-y-1">
                @php
                    $currentRoute = request()->route()?->getName();
                    $currentRole = auth()->user()?->getCurrentRole();
                @endphp

                {{-- Главная --}}
                <a href="{{ url('/dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                       {{ $currentRoute === 'dashboard' ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span x-show="sidebarOpen" x-transition>Главная</span>
                </a>

                {{-- Раздел для Администратора --}}
                @if($currentRole?->slug === 'admin')
                <div class="pt-4">

                    <a href="{{ route('admin.staff.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'admin.staff') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Администраторы</span>
                    </a>
                </div>
                @endif

                {{-- Раздел для Производителя --}}
                @if($currentRole?->slug === 'manufacturer')
                <div class="pt-4">

                    <a href="{{ route('manufacturer.profile') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'manufacturer.profile') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Профиль</span>
                    </a>

                    <a href="{{ route('manufacturer.warehouses.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'manufacturer.warehouses') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Склады</span>
                    </a>

                    <a href="{{ route('manufacturer.catalog.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'manufacturer.catalog') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Каталог</span>
                    </a>

                    <a href="{{ route('manufacturer.products.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'manufacturer.products') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Номенклатура</span>
                    </a>
                </div>
                @endif
            </nav>
        </aside>
        {{-- Main --}}
        <div :class="sidebarOpen ? 'ml-64' : 'ml-20'" class="flex-1 transition-all duration-300">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-6">
                <h1 class="text-lg font-semibold">@yield('heading', 'Главная')</h1>
                <div class="flex items-center gap-4">
                    @auth
                    <span class="text-sm text-gray-500">{{ auth()->user()->email }}</span>
                    <form method="POST" action="{{ url('/logout') }}" class="inline">@csrf<button type="submit" class="text-sm text-red-600 hover:underline">Выход</button></form>
                    @else
                    <a href="{{ url('/login') }}" class="text-sm text-[#c3242a] hover:underline">Вход</a>
                    @endauth
                </div>
            </header>
            <main class="p-6">
                @yield('content')
            </main>
        </div>
    </div>

    @auth
    @if(auth()->user()->needsRoleSelection())
    {{-- Модальное окно выбора роли (при нескольких ролях сразу после авторизации) --}}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" aria-modal="true" role="dialog" aria-labelledby="role-select-title">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-xl p-6" @click.stop>
            <h2 id="role-select-title" class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Выберите, в каком качестве вы хотите войти</h2>
            <form method="POST" action="{{ route('role.store') }}">
                @csrf
                <div class="space-y-3 mb-6">
                    @foreach(auth()->user()->roles as $role)
                        @php
                            $companyName = $role->pivot->company_name ?? null;
                            $optionLabel = $role->labelWithCompany($companyName);
                        @endphp
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-600 p-3 transition hover:bg-gray-50 dark:hover:bg-gray-700/50 has-[:checked]:border-[#c3242a] has-[:checked]:ring-2 has-[:checked]:ring-[#c3242a]/20">
                            <input type="radio" name="role_id" value="{{ $role->id }}" required
                                class="h-4 w-4 shrink-0 border-gray-300 text-[#c3242a] focus:ring-[#c3242a]" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $optionLabel }}</span>
                        </label>
                    @endforeach
                </div>
                @error('role_id')<p class="mb-3 text-sm text-red-500">{{ $message }}</p>@enderror
                <button type="submit"
                    class="w-full rounded-lg bg-[#c3242a] px-4 py-3 text-sm font-medium text-white hover:bg-[#a01e24] transition">
                    Продолжить
                </button>
            </form>
        </div>
    </div>
    @endif
    @endauth

    @stack('scripts')
</body>
</html>
