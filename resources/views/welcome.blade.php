<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Yemen POS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased flex items-center justify-center p-6">
    <main class="max-w-lg w-full bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 font-bold text-xl mb-4">
            POS
        </div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">نظام نقاط البيع اليمني — Yemen POS</h1>
        <p class="text-sm text-slate-600 leading-relaxed mb-6">
            النواة الخفيفة جاهزة للعمل أوفلاين بسرعة فائقة ودقة مالية صارمة.
        </p>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>المنطقة الزمنية: {{ config('app.timezone') }}</span>
        </div>
    </main>
</body>
</html>
