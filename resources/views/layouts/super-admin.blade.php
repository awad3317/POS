<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <title>@yield('title', 'إدارة المنصة والاشتراكات') — Yemen POS SaaS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 font-sans antialiased">
    <!-- Top SaaS Bar -->
    <header class="bg-slate-900 text-white border-b border-slate-800 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-600 flex items-center justify-center font-extrabold text-sm shadow-sm">
                    SaaS
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('super-admin.index') }}" class="font-bold text-base text-white hover:text-emerald-300 transition">
                            لوحة إدارة منصة Yemen POS
                        </a>
                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[11px] font-bold">
                            Super Admin
                        </span>
                    </div>
                    <p class="text-xs text-slate-400">إدارة المتاجر والاشتراكات وحسابات المُلّاك مركزياً</p>
                </div>
            </div>

            <div class="flex items-center gap-3 text-xs">
                <span class="text-slate-300 font-medium">{{ auth()->user()?->name }} ({{ auth()->user()?->email }})</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold transition">
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
        @if(session('success'))
            <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm font-semibold">
                ✓ {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                <div class="font-bold">يرجى تصحيح الأخطاء التالية:</div>
                @foreach($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
