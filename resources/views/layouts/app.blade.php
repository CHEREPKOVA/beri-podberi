<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Личный кабинет') — {{ config('app.name') }}</title>
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
        .form-control-brand {
            @apply w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm
                focus:outline-none focus:border-[#c3242a] focus:ring-2 focus:ring-[#c3242a]/25;
        }
        .flatpickr-calendar {
            font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif !important;
            box-shadow: 0 10px 30px rgba(0,0,0,.12) !important;
            border: 1px solid #fecaca !important;
        }
        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange,
        .flatpickr-day.selected:hover {
            background: #c3242a !important;
            border-color: #c3242a !important;
        }
        .flatpickr-day.today {
            border-color: #c3242a !important;
        }
        .flatpickr-months .flatpickr-month,
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-weekday {
            color: #c3242a !important;
            fill: #c3242a !important;
        }
        .ts-wrapper.single .ts-control {
            border-radius: 0.5rem !important;
            border-color: #d1d5db !important;
            min-height: 2.5rem !important;
            padding: 0.4rem 0.75rem !important;
            box-shadow: none !important;
        }
        .ts-wrapper.focus .ts-control,
        .ts-wrapper.single.input-active .ts-control {
            border-color: #c3242a !important;
            box-shadow: 0 0 0 3px rgba(195, 36, 42, 0.2) !important;
        }
        .ts-dropdown .option.active {
            background-color: #c3242a !important;
        }
        .ts-dropdown .option:hover {
            background-color: #fee2e2 !important;
            color: #991b1b !important;
        }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200"
      x-data="{
          sidebarOpen: true,
          cartItemsCount: {{ (int) ($sidebarCartItemsCount ?? $buyerCartItemsCount ?? 0) }},
          toast: null,
          toastOk: true,
          toastTimer: null,
          showToast(message, ok = true) {
              this.toast = message;
              this.toastOk = ok !== false;
              clearTimeout(this.toastTimer);
              this.toastTimer = setTimeout(() => { this.toast = null; }, 4500);
          },
          setCartCount(count) {
              this.cartItemsCount = Number(count) || 0;
          }
      }"
      @cart-updated.window="if ($event.detail.count != null) setCartCount($event.detail.count); if ($event.detail.message) showToast($event.detail.message, $event.detail.success !== false)">
    @php
        $authUser = auth()->user();
        $activeRoles = $authUser?->activeRoles() ?? collect();
        $canSwitchRole = $activeRoles->count() > 1;
        $currentRole = $authUser?->getCurrentRole();
        $currentRolePivot = $authUser?->roles?->firstWhere('id', $currentRole?->id);
        $currentRoleCompany = $currentRolePivot?->pivot?->company_name ?? null;
        $currentRoleLabel = $currentRole ? $currentRole->labelWithCompany($currentRoleCompany) : '—';
        $isAdminPanelRole = $currentRole?->isAdminPanel() ?? false;
        $canManageStaff = $authUser?->hasPermission('staff.manage') ?? false;
        $canManageCompanies = $authUser?->hasPermission('companies.manage') ?? false;
        $canManageOrders = $authUser?->hasPermission('orders.manage') ?? false;
        $canManageCatalog = $authUser?->hasPermission('catalog.manage') ?? false;
        $canManageDirectories = $authUser?->hasPermission('directories.manage') ?? false;
        $canViewAudit = $authUser?->hasPermission('audit.view') ?? false;
    @endphp
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
                    $sidebarCart = match ($currentRole?->slug) {
                        'end_company', 'company_employee' => [
                            'url' => route('buyer.cart.index'),
                            'active' => str_starts_with($currentRoute ?? '', 'buyer.cart')
                                || str_starts_with($currentRoute ?? '', 'buyer.checkout'),
                        ],
                        'distributor' => [
                            'url' => route('distributor.purchases.cart.index'),
                            'active' => str_starts_with($currentRoute ?? '', 'distributor.purchases.cart')
                                || str_starts_with($currentRoute ?? '', 'distributor.purchases.checkout'),
                        ],
                        default => null,
                    };
                @endphp
                @if($sidebarCart)
                    <a href="{{ $sidebarCart['url'] }}"
                       class="relative mb-3 flex items-center gap-3 px-3 py-2.5 rounded-xl border-2 transition-colors
                           {{ $sidebarCart['active']
                               ? 'border-[#c3242a] bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400'
                               : 'border-[#c3242a]/70 text-[#c3242a] hover:bg-red-50 dark:hover:bg-red-900/20' }}">
                        <span class="relative shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                            </svg>
                            <span x-show="cartItemsCount > 0"
                                  x-cloak
                                  class="absolute -top-2 -right-2 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-[#c3242a] text-white text-[10px] leading-[1.15rem] text-center font-semibold ring-2 ring-white dark:ring-gray-800"
                                  x-text="cartItemsCount > 99 ? '99+' : cartItemsCount"></span>
                        </span>
                        <span x-show="sidebarOpen" x-transition class="font-semibold">Корзина</span>
                    </a>
                @endif

                {{-- Главная --}}
                <a href="{{ url('/dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                       {{ $currentRoute === 'dashboard' ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span x-show="sidebarOpen" x-transition>Главная</span>
                </a>

                {{-- Раздел админ-панели (пункты по правам) --}}
                @if($isAdminPanelRole)
                <div class="pt-4">
                    @php
                        $adminCatalogActive = str_starts_with($currentRoute ?? '', 'admin.catalog');
                    @endphp

                    @if($canManageStaff)
                    <a href="{{ route('admin.staff.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'admin.staff') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Администраторы</span>
                    </a>
                    @endif

                    @if($canManageCompanies)
                    <a href="{{ route('admin.companies.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'admin.companies') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Компании</span>
                    </a>
                    @endif

                    @if($canManageOrders)
                    <a href="{{ route('admin.orders.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'admin.orders') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Заказы</span>
                    </a>
                    @endif

                    @if($canManageCatalog)
                    <a href="{{ route('admin.catalog.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ $adminCatalogActive ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Каталог</span>
                    </a>
                    @endif

                    @php
                        $adminDirectoriesActive = str_starts_with($currentRoute ?? '', 'admin.directories')
                            || str_starts_with($currentRoute ?? '', 'admin.regions')
                            || str_starts_with($currentRoute ?? '', 'admin.federal-districts')
                            || str_starts_with($currentRoute ?? '', 'admin.company-types')
                            || str_starts_with($currentRoute ?? '', 'admin.platform-roles')
                            || str_starts_with($currentRoute ?? '', 'admin.order-statuses')
                            || str_starts_with($currentRoute ?? '', 'admin.claim-statuses')
                            || str_starts_with($currentRoute ?? '', 'admin.delivery-methods')
                            || str_starts_with($currentRoute ?? '', 'admin.transport-companies')
                            || str_starts_with($currentRoute ?? '', 'admin.warehouse-types')
                            || str_starts_with($currentRoute ?? '', 'admin.unit-types')
                            || str_starts_with($currentRoute ?? '', 'admin.document-types');
                        $adminSettingsActive = str_starts_with($currentRoute ?? '', 'admin.system-settings');
                    @endphp
                    @if($canManageDirectories)
                    <a href="{{ route('admin.directories.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ $adminDirectoriesActive ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h7"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Справочники</span>
                    </a>
                    <a href="{{ route('admin.system-settings.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ $adminSettingsActive ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Настройки</span>
                    </a>
                    @endif

                    @if($canViewAudit)
                    <div class="pt-4 mt-2 border-t border-gray-200 dark:border-gray-700">
                        <p x-show="sidebarOpen" x-cloak class="px-3 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Контроль
                        </p>
                        <a href="{{ route('admin.audit.index') }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                               {{ str_starts_with($currentRoute ?? '', 'admin.audit') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            <span x-show="sidebarOpen" x-transition>Журнал</span>
                        </a>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Раздел для Производителя --}}
                @if($currentRole?->slug === 'manufacturer')
                @php
                    $manufacturerCatalogActive = str_starts_with($currentRoute ?? '', 'manufacturer.catalog');
                    $manufacturerPartnersActive = str_starts_with($currentRoute ?? '', 'manufacturer.partners');
                @endphp
                <div class="pt-4 space-y-1">
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

                    <a href="{{ route('manufacturer.products.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'manufacturer.products') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Номенклатура</span>
                    </a>
                    <a href="{{ route('manufacturer.orders.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'manufacturer.orders') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span class="relative shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            @if(($ordersAttentionCount ?? 0) > 0)
                                <span class="absolute -top-2 -right-2 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-[#c3242a] text-white text-[10px] leading-[1.15rem] text-center font-semibold ring-2 ring-white dark:ring-gray-800">
                                    {{ $ordersAttentionCount > 99 ? '99+' : $ordersAttentionCount }}
                                </span>
                            @endif
                        </span>
                        <span x-show="sidebarOpen" x-transition>Заказы</span>
                    </a>
                </div>

                <div class="pt-4 mt-2 border-t border-gray-200 dark:border-gray-700 space-y-1">
                    <a href="{{ route('manufacturer.catalog.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ $manufacturerCatalogActive ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Каталог товаров</span>
                    </a>

                    <a href="{{ route('manufacturer.partners.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ $manufacturerPartnersActive ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Компании</span>
                    </a>
                </div>
                @endif

                @if($currentRole?->slug === 'distributor')
                <div class="pt-4">
                    <a href="{{ route('distributor.profile') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'distributor.profile') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Профиль</span>
                    </a>
                    <a href="{{ route('distributor.warehouses.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'distributor.warehouses') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Склады</span>
                    </a>
                    <a href="{{ route('distributor.products.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'distributor.products') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Номенклатура</span>
                    </a>
                    <a href="{{ route('distributor.orders.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'distributor.orders') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span class="relative shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            @if(($ordersAttentionCount ?? 0) > 0)
                                <span class="absolute -top-2 -right-2 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-[#c3242a] text-white text-[10px] leading-[1.15rem] text-center font-semibold ring-2 ring-white dark:ring-gray-800">
                                    {{ $ordersAttentionCount > 99 ? '99+' : $ordersAttentionCount }}
                                </span>
                            @endif
                        </span>
                        <span x-show="sidebarOpen" x-transition>Заказы</span>
                    </a>
                    <a href="{{ route('distributor.purchases.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'distributor.purchases') && ! str_starts_with($currentRoute ?? '', 'distributor.purchases.cart') && ! str_starts_with($currentRoute ?? '', 'distributor.purchases.checkout') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span class="relative shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            @if(($purchasesAttentionCount ?? 0) > 0)
                                <span class="absolute -top-2 -right-2 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-[#c3242a] text-white text-[10px] leading-[1.15rem] text-center font-semibold ring-2 ring-white dark:ring-gray-800">
                                    {{ $purchasesAttentionCount > 99 ? '99+' : $purchasesAttentionCount }}
                                </span>
                            @endif
                        </span>
                        <span x-show="sidebarOpen" x-transition>Мои покупки</span>
                    </a>
                </div>
                @endif

                @if($currentRole?->slug === 'end_company')
                <div class="pt-4">
                    <a href="{{ route('end_company.profile') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'end_company.profile') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Профиль</span>
                    </a>
                </div>
                @endif

                @if(in_array($currentRole?->slug, ['distributor', 'end_company', 'company_employee'], true))
                <div class="pt-4">
                    <a href="{{ route('buyer.catalog.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'buyer.catalog') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        <span x-show="sidebarOpen" x-transition>Каталог</span>
                    </a>
                </div>
                @endif

                @if(in_array($currentRole?->slug, ['end_company', 'company_employee'], true))
                <div class="pt-4">
                    <a href="{{ route('buyer.orders.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2 rounded-lg transition-colors
                           {{ str_starts_with($currentRoute ?? '', 'buyer.orders') ? 'bg-red-50 dark:bg-red-900/20 text-[#c3242a] dark:text-red-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                        <span class="relative shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            @if(($ordersAttentionCount ?? 0) > 0)
                                <span class="absolute -top-2 -right-2 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-[#c3242a] text-white text-[10px] leading-[1.15rem] text-center font-semibold ring-2 ring-white dark:ring-gray-800">
                                    {{ $ordersAttentionCount > 99 ? '99+' : $ordersAttentionCount }}
                                </span>
                            @endif
                        </span>
                        <span x-show="sidebarOpen" x-transition>Заказы</span>
                    </a>
                </div>
                @endif
            </nav>
        </aside>
        {{-- Main --}}
        <div :class="sidebarOpen ? 'ml-64' : 'ml-20'" class="flex-1 transition-all duration-300">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-6">
                <h1 class="min-w-0 flex flex-wrap items-center gap-2.5 text-lg font-semibold">@yield('heading', 'Главная')</h1>
                <div class="flex items-center gap-4">
                    @auth
                    {{-- Уведомления в шапке — на следующем этапе
                    @php
                        $unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(8)->get();
                        $unreadCount = auth()->user()->unreadNotifications()->count();
                    @endphp
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="relative rounded-lg border border-gray-300 dark:border-gray-600 p-2 text-gray-600 dark:text-gray-300 hover:border-[#c3242a] hover:text-[#c3242a]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if($unreadCount > 0)
                                <span class="absolute -top-1 -right-1 min-w-[1.1rem] h-4 px-1 rounded-full bg-[#c3242a] text-white text-[10px] leading-4 text-center">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                            @endif
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak
                             class="absolute right-0 mt-2 w-80 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg z-50">
                            <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-900 dark:text-white">Уведомления</div>
                            <ul class="max-h-80 overflow-y-auto">
                                @forelse($unreadNotifications as $notification)
                                    <li class="px-3 py-2 text-sm border-b border-gray-100 dark:border-gray-700/60">
                                        <p class="text-gray-900 dark:text-white">{{ $notification->data['message'] ?? 'Уведомление' }}</p>
                                        <p class="text-[10px] text-gray-400 mt-1">{{ $notification->created_at?->format('d.m.Y H:i') }}</p>
                                    </li>
                                @empty
                                    <li class="px-3 py-4 text-sm text-gray-500">Нет новых уведомлений</li>
                                @endforelse
                            </ul>
                            @if($unreadCount > 0)
                                <form method="POST" action="{{ route('notifications.read_all') }}" class="p-2 border-t border-gray-200 dark:border-gray-700">
                                    @csrf
                                    <button type="submit" class="w-full text-xs text-[#c3242a] hover:underline">Отметить все прочитанными</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    --}}
                    <span class="text-sm text-gray-500">Вы вошли как: {{ $currentRoleLabel }}</span>
                    @if($canSwitchRole)
                    <button type="button"
                        onclick="document.getElementById('role-switch-modal')?.classList.remove('hidden')"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:border-[#c3242a] hover:text-[#c3242a] transition">
                        Сменить роль
                    </button>
                    @endif
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
    @if($canSwitchRole && !auth()->user()->needsRoleSelection())
    <div id="role-switch-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4" aria-modal="true" role="dialog" aria-labelledby="role-switch-title">
        <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-800 shadow-xl p-6">
            <div class="mb-6 flex items-start justify-between gap-3">
                <h2 id="role-switch-title" class="text-lg font-semibold text-gray-900 dark:text-white">Выберите новую активную роль</h2>
                <button type="button" onclick="document.getElementById('role-switch-modal')?.classList.add('hidden')" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">✕</button>
            </div>
            <form method="POST" action="{{ route('role.switch') }}">
                @csrf
                <div class="space-y-3 mb-6">
                    @foreach($activeRoles as $role)
                        @php
                            $companyName = $role->pivot->company_name ?? null;
                            $optionLabel = $role->labelWithCompany($companyName);
                        @endphp
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 dark:border-gray-600 p-3 transition hover:bg-gray-50 dark:hover:bg-gray-700/50 has-[:checked]:border-[#c3242a] has-[:checked]:ring-2 has-[:checked]:ring-[#c3242a]/20">
                            <input type="radio" name="role_id" value="{{ $role->id }}" @checked($currentRole?->id === $role->id) required
                                class="h-4 w-4 shrink-0 border-gray-300 accent-[#c3242a] focus:ring-[#c3242a]" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $optionLabel }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit"
                    class="w-full rounded-lg bg-[#c3242a] px-4 py-3 text-sm font-medium text-white hover:bg-[#a01e24] transition">
                    Продолжить
                </button>
            </form>
        </div>
    </div>
    @endif
    @endauth

    <div x-show="toast"
         x-cloak
         x-transition.opacity.duration.200ms
         class="fixed bottom-6 right-6 z-[80] max-w-sm rounded-xl border bg-white dark:bg-gray-800 shadow-xl px-4 py-3"
         :class="toastOk ? 'border-[#c3242a]/30' : 'border-amber-300'">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white"
                  :class="toastOk ? 'bg-[#c3242a]' : 'bg-amber-500'">
                <svg x-show="toastOk" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="!toastOk" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 100 14 7 7 0 000-14z"/></svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="toastOk ? 'Добавлено в корзину' : 'Не удалось добавить'"></p>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5" x-text="toast"></p>
            </div>
            <button type="button" class="ml-auto text-gray-400 hover:text-gray-600" @click="toast = null">✕</button>
        </div>
    </div>

    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function sanitizeQty(raw) {
            const digits = String(raw ?? '').replace(/\D+/g, '');
            if (!digits) return '';
            const normalized = digits.replace(/^0+/, '');
            return normalized || '';
        }

        function resolveMinQty(form, qtyInput) {
            const fromInput = Number(qtyInput?.dataset?.minQty || 0);
            const fromForm = Number(form?.dataset?.minQty || 0);
            const select = form?.querySelector?.('.js-cart-offer-select');
            const selected = select?.selectedOptions?.[0];
            const fromSelect = Number(selected?.dataset?.minQty || 0);
            const minQty = Math.max(fromInput, fromForm, fromSelect, 1);
            return Number.isFinite(minQty) ? minQty : 1;
        }

        function bindQtyInput(input) {
            if (!input || input.dataset.qtyBound === '1') return;
            input.dataset.qtyBound = '1';
            input.addEventListener('input', () => {
                const next = sanitizeQty(input.value);
                if (input.value !== next) input.value = next;
            });
            input.addEventListener('blur', () => {
                const minQty = resolveMinQty(input.closest('form'), input);
                const next = sanitizeQty(input.value);
                const num = Number(next || 0);
                input.value = String(num >= minQty ? num : minQty);
            });
            input.addEventListener('keydown', (e) => {
                if (['e', 'E', '+', '-', '.', ','].includes(e.key)) e.preventDefault();
            });
        }

        function syncOfferMinQty(select) {
            const form = select.closest('form');
            const option = select.selectedOptions?.[0];
            const minQty = Math.max(1, Number(option?.dataset?.minQty || 1));
            if (form) form.dataset.minQty = String(minQty);
            const qtyInput = form?.querySelector?.('.js-cart-qty');
            if (qtyInput) {
                qtyInput.dataset.minQty = String(minQty);
                const current = Number(sanitizeQty(qtyInput.value) || 0);
                if (current < minQty) qtyInput.value = String(minQty);
            }
        }

        async function submitAddToCart(form) {
            const qtyInput = form.querySelector('.js-cart-qty, input[name="quantity"]');
            const minQty = resolveMinQty(form, qtyInput);
            if (qtyInput) {
                const qty = sanitizeQty(qtyInput.value);
                qtyInput.value = qty || String(minQty);
                if (!qty || Number(qty) < 1) {
                    window.dispatchEvent(new CustomEvent('cart-updated', {
                        detail: { count: null, message: 'Укажите количество больше 0.', success: false }
                    }));
                    return;
                }
                if (Number(qty) < minQty) {
                    qtyInput.value = String(minQty);
                    window.dispatchEvent(new CustomEvent('cart-updated', {
                        detail: {
                            count: null,
                            message: `Минимальная партия — ${minQty} шт. Укажите количество не меньше ${minQty}.`,
                            success: false,
                        }
                    }));
                    return;
                }
            }

            const btn = form.querySelector('.js-add-to-cart-btn, button[type="submit"]');
            if (btn) btn.disabled = true;

            try {
                const response = await fetch(form.getAttribute('action') || form.dataset.cartStoreUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: new FormData(form),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const firstError = payload?.errors
                        ? Object.values(payload.errors).flat()[0]
                        : (payload?.message || 'Не удалось добавить товар в корзину.');
                    window.dispatchEvent(new CustomEvent('cart-updated', {
                        detail: { count: null, message: firstError, success: false }
                    }));
                    return;
                }

                window.dispatchEvent(new CustomEvent('cart-updated', {
                    detail: {
                        count: payload.cart_items_count,
                        message: payload.message || 'Товар добавлен в корзину.',
                        success: true,
                    }
                }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('cart-updated', {
                    detail: { count: null, message: 'Ошибка сети. Попробуйте ещё раз.', success: false }
                }));
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        document.addEventListener('change', (e) => {
            if (e.target?.matches?.('.js-cart-offer-select')) syncOfferMinQty(e.target);
        });

        document.addEventListener('input', (e) => {
            if (e.target?.matches?.('.js-cart-qty')) bindQtyInput(e.target);
        });

        document.addEventListener('focusin', (e) => {
            if (e.target?.matches?.('.js-cart-qty')) bindQtyInput(e.target);
        });

        document.addEventListener('submit', (e) => {
            const form = e.target?.closest?.('form.js-add-to-cart');
            if (!form) return;
            e.preventDefault();
            submitAddToCart(form);
        });

        document.querySelectorAll('.js-cart-qty').forEach(bindQtyInput);
        document.querySelectorAll('.js-cart-offer-select').forEach((select) => {
            if (select.value) syncOfferMinQty(select);
        });
    })();
    </script>
    @stack('scripts')
</body>
</html>
