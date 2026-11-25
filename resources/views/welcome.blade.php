<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}?v=2">
    <title>{{ config('app.name', 'تيار بلس') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F9FAFB] text-slate-900 font-sans antialiased flex items-center justify-center p-6">
    <div class="max-w-lg w-full space-y-6">
        <main class="rounded-2xl border border-gray-200 bg-white shadow-xs p-8 sm:p-10 text-center space-y-6">
            <div class="space-y-3">
                <x-brand-logo size="xl" rounded="rounded-2xl" class="mx-auto" />

                <div class="space-y-1">
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">تيار بلس</h1>
                    <p class="text-xs font-semibold text-emerald-700">نظام نقاط البيع والمحاسبة السحابي</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 leading-relaxed max-w-sm mx-auto">
                النواة الخفيفة جاهزة للعمل أوفلاين بسرعة فائقة ودقة مالية صارمة.
            </p>

            <div class="pt-2 border-t border-gray-100 flex flex-wrap items-center justify-center gap-2">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>المنطقة الزمنية:</span>
                    <span class="font-mono tabular-nums" dir="ltr">{{ config('app.timezone') }}</span>
                </div>
            </div>
        </main>

        <p class="text-center text-[11px] text-gray-400 tabular-nums">
            © {{ date('Y') }} تيار بلس — جميع الحقوق محفوظة
        </p>
    </div>
</body>
</html>