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
<body class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased" x-data="{ sidebarOpen: false }">
@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $currentStore = $authUser?->store;
    $currency = $currentStore?->currency_symbol ?? 'ر.ي';
@endphp

<div class="min-h-screen flex">
    <!-- Mobile Backdrop -->
    <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden" style="display: none;"></div>

    <!-- RTL Sidebar -->
    <aside :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
           class="fixed lg:static inset-y-0 right-0 z-40 w-64 bg-slate-900 text-slate-100 flex flex-col transition-transform duration-200 ease-in-out shrink-0">
        <div class="p-4 border-b border-slate-800 flex items-center justify-between">
            <div>
                <div class="text-lg font-bold text-emerald-400 truncate">{{ $currentStore?->name ?? 'Yemen POS' }}</div>
                <div class="text-xs text-slate-400">نظام نقاط البيع اليمني</div>
            </div>
            <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white p-1">✕</button>
        </div>

        <nav class="flex-1 overflow-y-auto p-3 space-y-1 text-sm">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('dashboard') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>📊</span><span>الرئيسية</span>
            </a>

            @can('pos.sell')
            <a href="{{ route('pos.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25 font-semibold border border-emerald-500/30">
                <span>⚡</span><span>شاشة الكاشير (/pos)</span>
            </a>
            @endcan

            @can('products.manage')
            <a href="{{ route('products.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('products.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>📦</span><span>المنتجات والباركود</span>
            </a>
            <a href="{{ route('barcodes.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('barcodes.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>🏷️</span><span>طباعة ملصقات الباركود</span>
            </a>
            <a href="{{ route('stocktake.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('stocktake.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>📋</span><span>الجرد وكرت الصنف</span>
            </a>
            @endcan

            @can('suppliers.manage')
            <a href="{{ route('suppliers.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('suppliers.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>🚚</span><span>حسابات الموردين</span>
            </a>
            <a href="{{ route('purchases.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('purchases.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>🧾</span><span>فواتير المشتريات</span>
            </a>
            @endcan

            <a href="{{ route('customers.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('customers.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>👥</span><span>العملاء ودفتر الديون</span>
            </a>

            @can('employees.manage')
            <a href="{{ route('employees.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('employees.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>👷</span><span>العمال والرواتب والسلف</span>
            </a>
            @endcan

            <a href="{{ route('orders.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('orders.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>🛍️</span><span>الفواتير والمرتجعات</span>
            </a>

            <a href="{{ route('shifts.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('shifts.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>🔐</span><span>الورديات والصندوق</span>
            </a>

            <a href="{{ route('expenses.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('expenses.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>💸</span><span>المصروفات اليومية</span>
            </a>

            @can('reports.view_profit')
            <a href="{{ route('owner.dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('owner.dashboard') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>📱</span><span>لوحة المالك السريعة</span>
            </a>
            <a href="{{ route('reports.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('reports.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>📈</span><span>التقارير وصافي الربح</span>
            </a>
            @endcan

            @can('settings.manage')
            <a href="{{ route('settings.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg {{ request()->routeIs('settings.*') ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800' }}">
                <span>⚙️</span><span>الإعدادات والأجهزة</span>
            </a>
            @endcan
        </nav>

        <div class="p-3 border-t border-slate-800 text-xs text-slate-400 flex items-center justify-between">
            <span class="truncate">{{ $authUser?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-rose-400 hover:text-rose-300 font-medium">خروج</button>
            </form>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Header -->
        <header class="bg-white border-b border-slate-200 px-4 lg:px-6 py-3 flex items-center justify-between gap-3 sticky top-0 z-20">
            <div class="flex items-center gap-3">
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg border border-slate-200 text-slate-600">☰</button>
                <h1 class="text-lg font-bold text-slate-800">@yield('header', 'لوحة الإدارة')</h1>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                @if($currentStore?->secondary_currency_code && $currentStore?->exchange_rate)
                    <span class="px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-xs font-semibold">
                        1 {{ $currentStore->secondary_currency_code }} = {{ number_format((float) $currentStore->exchange_rate, 0) }} {{ $currency }}
                    </span>
                @endif

                <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                    {{ $authUser?->getRoleNames()->first() ?? 'موظف' }}
                </span>

                <a href="{{ route('pos.index') }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-bold shadow-sm transition">
                    <span>افتح شاشة الكاشير</span>
                </a>
            </div>
        </header>

        <!-- Floating Toast Alerts -->
        <div class="px-4 lg:px-6 pt-4 space-y-2">
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                     class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                    <span>✅ {{ session('success') }}</span>
                    <button @click="show = false" class="text-emerald-600 px-2">✕</button>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }" x-show="show"
                     class="flex items-center justify-between p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-medium">
                    <span>⚠️ {{ session('error') }}</span>
                    <button @click="show = false" class="text-rose-600 px-2">✕</button>
                </div>
            @endif

            @if($errors->any())
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm">
                    <div class="font-bold mb-1">يرجى تصحيح الأخطاء التالية:</div>
                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <main class="flex-1 p-4 lg:p-6">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
