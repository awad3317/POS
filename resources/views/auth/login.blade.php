<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول — Yemen POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4 font-sans antialiased">
<div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8">
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-600 text-white font-bold text-lg mb-3">POS</div>
        <h1 class="text-xl font-bold text-slate-900">تسجيل الدخول للنظام</h1>
        <p class="text-xs text-slate-500 mt-1">أدخل البريد الإلكتروني أو رقم الهاتف وكلمة المرور</p>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">البريد الإلكتروني أو رقم الجوال</label>
            <input type="text" name="login" value="{{ old('login') }}" required autofocus
                   class="w-full rounded-xl border-slate-300 text-sm px-3.5 py-2.5 border focus:border-emerald-600 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">كلمة المرور</label>
            <input type="password" name="password" required
                   class="w-full rounded-xl border-slate-300 text-sm px-3.5 py-2.5 border focus:border-emerald-600 focus:outline-none">
        </div>

        <label class="inline-flex items-center gap-2 text-xs text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-emerald-600">
            <span>تذكرني على هذا الجهاز</span>
        </label>

        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition">
            دخول
        </button>
    </form>
</div>
</body>
</html>
