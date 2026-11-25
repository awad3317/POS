<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#059669">
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}?v=2">
    <title>@yield('title', 'إدارة المنصة والاشتراكات') — تيار بلس</title>
    @hasSection('vite_entry')
        @yield('vite_entry')
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-[#F9FAFB] text-gray-800 font-sans antialiased"
      x-data="{ userMenuOpen: false }">
@php
    $authUser = auth()->user();
    $userInitial = mb_substr(trim((string) ($authUser?->name ?? 'م')), 0, 1);
@endphp

<div class="flex min-h-screen flex-col">

    {{-- TailAdmin Top SaaS Bar --}}
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white print:hidden">
        <div class="mx-auto flex h-[70px] w-full max-w-screen-2xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">

            {{-- Brand & Title --}}
            <a href="{{ route('super-admin.index') }}" class="flex min-w-0 items-center gap-3">
                <x-brand-logo size="md" />
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-bold text-gray-900 sm:text-base">
                            لوحة إدارة منصة تيار بلس
                        </span>
                        <span class="hidden items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 sm:inline-flex">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            مدير المنصة
                        </span>
                    </div>
                    <p class="hidden truncate text-[11px] text-gray-500 sm:block">
                        إدارة المتاجر والاشتراكات وحسابات المُلّاك مركزياً
                    </p>
                </div>
            </a>

            {{-- Left Utility Actions & User Dropdown --}}
            <div class="flex shrink-0 items-center gap-2.5">
                @hasSection('header_actions')
                    @yield('header_actions')
                @endif

                <div class="relative" @click.outside="userMenuOpen = false">
                    <button type="button" @click="userMenuOpen = !userMenuOpen"
                            class="flex h-10 items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-2.5 text-right transition hover:bg-gray-50">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-900 text-xs font-bold text-white">
                            {{ $userInitial }}
                        </div>
                        <div class="hidden text-right sm:block">
                            <div class="max-w-[140px] truncate text-xs font-semibold leading-tight text-gray-800">
                                {{ $authUser?->name }}
                            </div>
                            <div class="max-w-[140px] truncate text-[10px] text-gray-500">
                                {{ $authUser?->email }}
                            </div>
                        </div>
                        <svg class="h-4 w-4 text-gray-400 transition-transform" :class="userMenuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="userMenuOpen"
                         x-transition
                         style="display: none;"
                         class="absolute left-0 mt-2 w-60 rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg">
                        <div class="border-b border-gray-100 px-3 py-2">
                            <div class="truncate text-xs font-bold text-gray-900">{{ $authUser?->name }}</div>
                            <div class="truncate text-[11px] text-gray-500">{{ $authUser?->email }}</div>
                            <div class="mt-1 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">
                                صلاحية مدير المنصة العام
                            </div>
                        </div>

                        <div class="pt-1">
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
        </div>
    </header>

    {{-- Alerts Area --}}
    @if (session('success') || session('error') || $errors->any())
    <div class="mx-auto w-full max-w-screen-2xl space-y-2.5 px-4 pt-5 sm:px-6 lg:px-8 print:hidden">
        @if (session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
                 class="flex items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                <div class="flex items-center gap-2.5">
                    <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="show = false" class="rounded-md p-1 text-emerald-600 transition hover:bg-emerald-100" aria-label="إغلاق">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if (session('error'))
            <div x-data="{ show: true }" x-show="show" x-transition
                 class="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                <div class="flex items-center gap-2.5">
                    <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="show = false" class="rounded-md p-1 text-rose-600 transition hover:bg-rose-100" aria-label="إغلاق">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
                <div class="mb-1.5 flex items-center gap-2 text-sm font-bold">
                    <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>يرجى مراجعة الحقول التالية:</span>
                </div>
                <ul class="list-disc space-y-0.5 pr-6 text-xs">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    @endif

    {{-- Main Container --}}
    <main class="mx-auto w-full max-w-screen-2xl flex-1 space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @yield('content')
    </main>

</div>
</body>
</html>