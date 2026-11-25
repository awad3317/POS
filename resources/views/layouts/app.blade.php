<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <title>@yield('title', 'لوحة الإدارة') — {{ auth()->user()?->store?->name ?? 'Yemen POS' }}</title>
    @hasSection('vite_entry')
        @yield('vite_entry')
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-[#F9FAFB] text-gray-800 font-sans antialiased"
      x-data="{
          mobileOpen: false,
          sidebarCollapsed: localStorage.getItem('yp_sidebar_collapsed') === 'true',
          userMenuOpen: false,
          toggleCollapse() {
              this.sidebarCollapsed = !this.sidebarCollapsed;
              localStorage.setItem('yp_sidebar_collapsed', this.sidebarCollapsed);
          }
      }">
@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $currentStore = $authUser?->store;
    $currency = $currentStore?->currency_symbol ?? 'ر.ي';

    $roleLabels = [
        'owner'       => 'المالك',
        'manager'     => 'مدير الفرع',
        'storekeeper' => 'أمين المخزن',
        'cashier'     => 'كاشير',
        'super_admin' => 'مدير المنصة',
    ];
    $rawRole = $authUser?->getRoleNames()->first() ?? '';
    $roleLabel = $roleLabels[$rawRole] ?? 'موظف';

    $businessLabels = [
        'grocery'  => 'بقالة وسوبرماركت',
        'clothing' => 'ملابس وأحذية',
        'perfumes' => 'عطور ومستحضرات',
        'general'  => 'تجزئة عامة',
    ];
    $businessLabel = $businessLabels[$currentStore?->business_type ?? 'general'] ?? 'نظام نقاط البيع';

    $userInitial = mb_substr(trim((string) ($authUser?->name ?? 'م')), 0, 1);

    $lowStockCount = 0;
    if ($currentStore && $authUser?->can('products.manage')) {
        try {
            $lowStockCount = \App\Models\Product::forStore($currentStore->id)
                ->where('is_active', true)
                ->whereColumn('quantity', '<=', 'min_stock_alert')
                ->count();
        } catch (\Throwable) {
            $lowStockCount = 0;
        }
    }

    $navBase   = 'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors';
    $navActive = 'bg-emerald-50 text-emerald-700 font-semibold';
    $navIdle   = 'text-gray-700 hover:bg-gray-100 hover:text-gray-900';
@endphp

<div class="flex min-h-screen overflow-x-hidden">

    {{-- Mobile Backdrop --}}
    <div x-show="mobileOpen"
         x-transition.opacity.duration.200ms
         @click="mobileOpen = false"
         style="display: none;"
         class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-[1px] lg:hidden print:hidden"></div>

    {{-- TailAdmin Light RTL Sidebar --}}
    <aside :class="[
               mobileOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0',
               sidebarCollapsed ? 'lg:w-[82px]' : 'lg:w-[272px]'
           ]"
           class="fixed inset-y-0 right-0 z-50 flex w-[272px] shrink-0 flex-col border-l border-gray-200 bg-white transition-all duration-200 ease-in-out lg:static print:hidden">

        {{-- Store Brand Header --}}
        <div class="flex h-[70px] shrink-0 items-center justify-between border-b border-gray-100 px-4">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                @if($currentStore?->logo_path)
                    <img src="{{ asset('storage/' . $currentStore->logo_path) }}" alt="{{ $currentStore->name }}"
                         class="h-9 w-9 shrink-0 rounded-xl border border-gray-200 object-cover">
                @else
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-emerald-400 shadow-xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                @endif
                <div class="min-w-0" x-show="!sidebarCollapsed" x-transition.opacity>
                    <div class="truncate text-sm font-bold text-gray-900">{{ $currentStore?->name ?? 'Yemen POS' }}</div>
                    <div class="truncate text-[11px] text-gray-500">{{ $businessLabel }}</div>
                </div>
            </a>

            <button type="button" @click="mobileOpen = false"
                    class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 lg:hidden"
                    aria-label="إغلاق القائمة">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Sidebar Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-3">
            {{-- Primary POS Launch Button --}}
            @can('pos.sell')
            <div class="mb-3">
                <a href="{{ route('pos.index') }}"
                   title="شاشة الكاشير"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="flex items-center gap-3 rounded-xl bg-emerald-600 px-3.5 py-2.5 text-[13px] font-bold text-white shadow-xs transition hover:bg-emerald-700">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.85">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">شاشة الكاشير</span>
                </a>
            </div>
            @endcan

            {{-- Group 1: Overview & Sales --}}
            <div x-show="!sidebarCollapsed" class="px-3 pb-1.5 pt-2 text-[11px] font-semibold tracking-wide text-gray-400">
                نظرة عامة والمبيعات
            </div>
            <div class="space-y-1">
                <a href="{{ route('dashboard') }}" title="الرئيسية"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('dashboard') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('dashboard') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-3a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">الرئيسية</span>
                </a>

                @can('reports.view_profit')
                <a href="{{ route('owner.dashboard') }}" title="لوحة المالك السريعة"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('owner.dashboard') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('owner.dashboard') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">لوحة المالك السريعة</span>
                </a>
                @endcan

                <a href="{{ route('orders.index') }}" title="الفواتير والمرتجعات"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('orders.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('orders.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">الفواتير والمرتجعات</span>
                </a>

                <a href="{{ route('shifts.index') }}" title="الورديات والصندوق"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('shifts.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('shifts.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">الورديات والصندوق</span>
                </a>
            </div>

            {{-- Group 2: Inventory & Products --}}
            @can('products.manage')
            <div x-show="!sidebarCollapsed" class="px-3 pb-1.5 pt-5 text-[11px] font-semibold tracking-wide text-gray-400">
                المخزون والمنتجات
            </div>
            <div x-show="sidebarCollapsed" style="display: none;" class="my-2.5 border-t border-gray-100"></div>
            <div class="space-y-1">
                <a href="{{ route('products.index') }}" title="المنتجات والباركود"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('products.index', 'products.create', 'products.edit') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('products.index', 'products.create', 'products.edit') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="flex-1 truncate">المنتجات والباركود</span>
                    @if($lowStockCount > 0)
                        <span x-show="!sidebarCollapsed"
                              title="أصناف وصلت لحد النواقص"
                              class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white">
                            {{ $lowStockCount > 99 ? '+99' : $lowStockCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('barcodes.index') }}" title="طباعة ملصقات الباركود"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('barcodes.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('barcodes.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">طباعة ملصقات الباركود</span>
                </a>

                <a href="{{ route('stocktake.index') }}" title="الجرد وكرت الصنف"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('stocktake.*', 'products.stock-card') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('stocktake.*', 'products.stock-card') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">الجرد وكرت الصنف</span>
                </a>
            </div>
            @endcan

            {{-- Group 3: Finance & Accounts --}}
            <div x-show="!sidebarCollapsed" class="px-3 pb-1.5 pt-5 text-[11px] font-semibold tracking-wide text-gray-400">
                المالية والحسابات
            </div>
            <div x-show="sidebarCollapsed" style="display: none;" class="my-2.5 border-t border-gray-100"></div>
            <div class="space-y-1">
                @can('suppliers.manage')
                <a href="{{ route('purchases.index') }}" title="فواتير المشتريات"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('purchases.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('purchases.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">فواتير المشتريات</span>
                </a>

                <a href="{{ route('suppliers.index') }}" title="حسابات الموردين"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('suppliers.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('suppliers.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">حسابات الموردين</span>
                </a>
                @endcan

                <a href="{{ route('customers.index') }}" title="العملاء ودفتر الديون"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('customers.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('customers.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">العملاء ودفتر الديون</span>
                </a>

                @can('employees.manage')
                <a href="{{ route('employees.index') }}" title="العمال والرواتب والسلف"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('employees.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('employees.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">العمال والرواتب والسلف</span>
                </a>
                @endcan

                <a href="{{ route('expenses.index') }}" title="المصروفات اليومية"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('expenses.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('expenses.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">المصروفات اليومية</span>
                </a>
            </div>

            {{-- Group 4: Reports & System --}}
            @if($authUser?->can('reports.view_profit') || $authUser?->can('settings.manage'))
            <div x-show="!sidebarCollapsed" class="px-3 pb-1.5 pt-5 text-[11px] font-semibold tracking-wide text-gray-400">
                التقارير والنظام
            </div>
            <div x-show="sidebarCollapsed" style="display: none;" class="my-2.5 border-t border-gray-100"></div>
            <div class="space-y-1">
                @can('reports.view_profit')
                <a href="{{ route('reports.index') }}" title="التقارير وصافي الربح"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('reports.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('reports.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">التقارير وصافي الربح</span>
                </a>
                @endcan

                @can('settings.manage')
                <a href="{{ route('settings.index') }}" title="الإعدادات والأجهزة"
                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-0' : ''"
                   class="{{ $navBase }} {{ request()->routeIs('settings.*') ? $navActive : $navIdle }}">
                    <svg class="h-5 w-5 shrink-0 {{ request()->routeIs('settings.*') ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">الإعدادات والأجهزة</span>
                </a>
                @endcan
            </div>
            @endif
        </nav>

        {{-- Sidebar User Footer --}}
        <div class="border-t border-gray-100 p-3">
            <div :class="sidebarCollapsed ? 'lg:justify-center' : 'justify-between'"
                 class="flex items-center gap-2.5 rounded-xl border border-gray-100 bg-gray-50/80 px-2.5 py-2">
                <div class="flex min-w-0 items-center gap-2.5">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                        {{ $userInitial }}
                    </div>
                    <div class="min-w-0" x-show="!sidebarCollapsed">
                        <div class="truncate text-xs font-semibold text-gray-900">{{ $authUser?->name }}</div>
                        <div class="truncate text-[11px] text-gray-500">{{ $roleLabel }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" x-show="!sidebarCollapsed">
                    @csrf
                    <button type="submit" title="تسجيل الخروج"
                            class="rounded-lg p-1.5 text-gray-400 transition hover:bg-rose-50 hover:text-rose-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main Workspace --}}
    <div class="flex min-w-0 flex-1 flex-col">

        {{-- TailAdmin Topbar --}}
        <header class="sticky top-0 z-30 flex h-[70px] items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 lg:px-6 print:hidden">
            <div class="flex min-w-0 items-center gap-3">
                {{-- Mobile Menu Toggle --}}
                <button type="button" @click="mobileOpen = true"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 lg:hidden"
                        aria-label="فتح القائمة">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Desktop Sidebar Collapse Toggle --}}
                <button type="button" @click="toggleCollapse()"
                        class="hidden h-10 w-10 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 lg:inline-flex"
                        title="طي أو توسيع القائمة الجانبية">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16"/>
                    </svg>
                </button>

                {{-- Page Title & Optional Breadcrumb --}}
                <div class="min-w-0">
                    <h1 class="truncate text-base font-bold text-gray-900 sm:text-lg">@yield('header', 'لوحة الإدارة')</h1>
                    @hasSection('breadcrumb')
                        <div class="hidden text-[11px] text-gray-500 sm:block">@yield('breadcrumb')</div>
                    @endif
                </div>
            </div>

            {{-- Left Utility Actions --}}
            <div class="flex shrink-0 items-center gap-2.5">
                {{-- Exchange Rate Pill --}}
                @if($currentStore?->secondary_currency_code && $currentStore?->exchange_rate)
                    <a href="{{ $authUser?->can('settings.manage') ? route('settings.index') : '#' }}"
                       title="سعر الصرف المعتمد حالياً"
                       class="hidden items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 md:inline-flex">
                        <svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>1 {{ $currentStore->secondary_currency_code }} = {{ format_money($currentStore->exchange_rate) }} {{ $currency }}</span>
                    </a>
                @endif

                {{-- Open POS CTA --}}
                @can('pos.sell')
                <a href="{{ route('pos.index') }}"
                   class="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-600 px-3.5 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700 sm:px-4 sm:text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span class="hidden sm:inline">افتح شاشة الكاشير</span>
                    <span class="sm:hidden">الكاشير</span>
                </a>
                @endcan

                {{-- User Profile Dropdown --}}
                <div class="relative" @click.outside="userMenuOpen = false">
                    <button type="button" @click="userMenuOpen = !userMenuOpen"
                            class="flex h-10 items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-2.5 text-right transition hover:bg-gray-50">
                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                            {{ $userInitial }}
                        </div>
                        <div class="hidden text-right sm:block">
                            <div class="max-w-[110px] truncate text-xs font-semibold leading-tight text-gray-800">{{ $authUser?->name }}</div>
                            <div class="text-[10px] text-gray-500">{{ $roleLabel }}</div>
                        </div>
                        <svg class="h-4 w-4 text-gray-400 transition-transform" :class="userMenuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="userMenuOpen"
                         x-transition
                         style="display: none;"
                         class="absolute left-0 mt-2 w-56 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg">
                        <div class="border-b border-gray-100 px-3 py-2">
                            <div class="truncate text-xs font-bold text-gray-900">{{ $authUser?->name }}</div>
                            <div class="truncate text-[11px] text-gray-500">{{ $authUser?->email }}</div>
                        </div>

                        <div class="py-1">
                            @can('settings.manage')
                            <a href="{{ route('settings.index') }}"
                               class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-100">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>إعدادات المتجر والأجهزة</span>
                            </a>
                            @endcan
                        </div>

                        <div class="border-t border-gray-100 pt-1">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    <span>تسجيل الخروج</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Alerts Area --}}
        @if(session('success') || session('error') || $errors->any())
        <div class="mx-auto w-full max-w-screen-2xl space-y-2.5 px-4 pt-5 lg:px-6 print:hidden">
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
                     class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    <div class="flex items-center gap-2.5">
                        <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" @click="show = false" class="rounded-md p-1 text-emerald-600 transition hover:bg-emerald-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-transition
                     class="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                    <div class="flex items-center gap-2.5">
                        <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" @click="show = false" class="rounded-md p-1 text-rose-600 transition hover:bg-rose-100">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                    <div class="mb-1.5 flex items-center gap-2 text-sm font-bold">
                        <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>يرجى مراجعة الحقول التالية:</span>
                    </div>
                    <ul class="list-disc space-y-0.5 pr-6 text-xs">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        @endif

        {{-- Main Content Container --}}
        <main class="mx-auto w-full max-w-screen-2xl flex-1 p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>