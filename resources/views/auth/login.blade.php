<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}?v=2">
    <title>تسجيل الدخول — تيار بلس</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F9FAFB] text-slate-900 flex items-center justify-center p-4 font-sans antialiased">
<div class="w-full max-w-md space-y-6">

    <!-- Brand Identity Header -->
   <div class="flex flex-col items-center text-center mb-6">
        <div class="w-24 h-24 rounded-2xl bg-white shadow-sm border border-slate-100 flex items-center justify-center p-2 mb-3">
            <img
                src="{{ asset('img/tayar-plus-logo.png') }}"
                alt="تيار بلس"
                class="w-full h-full object-contain"
            />
        </div>
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">تيار بلس</h1>
        <p class="text-xs font-medium text-slate-500 mt-1">نظام نقاط البيع والمحاسبة السحابي</p>
    </div>

    <!-- Login Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-6 sm:p-8 space-y-6">
        <div class="space-y-1">
            <h1 class="text-lg font-extrabold text-slate-900">تسجيل الدخول للنظام</h1>
            <p class="text-xs text-gray-500">أدخل البريد الإلكتروني أو رقم الهاتف وكلمة المرور للمتابعة</p>
        </div>

        @if ($errors->any())
            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold flex items-start gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" class="space-y-4" x-data="{ showPassword: false }">
            @csrf

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700">البريد الإلكتروني أو رقم الجوال</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </span>
                    <input type="text"
                           name="login"
                           value="{{ old('login') }}"
                           required
                           autofocus
                           placeholder="name@example.com أو 77XXXXXXX"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 pr-10 pl-3.5 py-2.5 text-sm text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold text-slate-700">كلمة المرور</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input type="password"
                           :type="showPassword ? 'text' : 'password'"
                           name="password"
                           required
                           placeholder="••••••••"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 pr-10 pl-10 py-2.5 text-sm text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <button type="button"
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 hover:text-slate-700 transition-colors cursor-pointer"
                            :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'">
                        <svg x-show="!showPassword" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg x-show="showPassword" style="display: none;" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                            <line x1="2" y1="2" x2="22" y2="22" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="pt-0.5">
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600 cursor-pointer select-none">
                    <input type="checkbox"
                           name="remember"
                           class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                    <span>تذكرني على هذا الجهاز</span>
                </label>
            </div>

            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                    <polyline points="10 17 15 12 10 7" />
                    <line x1="15" y1="12" x2="3" y2="12" />
                </svg>
                <span>دخول</span>
            </button>
        </form>
    </div>

    <!-- Subtle Footer -->
    <p class="text-center text-[11px] text-gray-400 tabular-nums">
        © {{ date('Y') }} تيار بلس — جميع الحقوق محفوظة
    </p>

</div>
</body>
</html>