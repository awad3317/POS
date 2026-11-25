<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}?v=2">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}?v=2">
    <title>شاشة الكاشير — {{ $store->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/pos.js'])
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').then(function (reg) {
                    reg.update().catch(function () {});
                }).catch(function () {});
            });
        }
    </script>
</head>
<body class="min-h-screen bg-[#F9FAFB] text-slate-900 font-sans antialiased select-none"
      x-data="posApp(@js($bootConfig))"
      x-init="init()"
      @keydown.window="handleGlobalKeydown($event)">

    <!-- Toast Notification -->
    <div x-show="toast.show" x-transition style="display: none;"
         class="fixed bottom-4 left-4 z-50 max-w-md px-4 py-3 rounded-2xl shadow-lg border text-xs font-bold flex items-center gap-2.5"
         :class="{
            'bg-emerald-600 text-white border-emerald-700': toast.type === 'success',
            'bg-rose-600 text-white border-rose-700': toast.type === 'error',
            'bg-amber-500 text-slate-950 border-amber-600': toast.type === 'warning',
         }">
        <span class="flex-1 leading-relaxed" x-text="toast.message"></span>
        <button type="button" @click="toast.show = false" class="p-1 rounded-lg opacity-80 hover:opacity-100 hover:bg-black/10 transition-colors cursor-pointer shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Terminal Activation Screen (shown if terminal_token missing) -->
    <template x-if="initialized && !terminalToken">
        <div class="min-h-screen flex items-center justify-center p-4 bg-slate-900">
            <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
                <div class="p-6 sm:p-8 space-y-6">
                    <div class="text-center space-y-2">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 text-white shadow-xs mx-auto">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                                <line x1="8" y1="21" x2="16" y2="21" />
                                <line x1="12" y1="17" x2="12" y2="21" />
                            </svg>
                        </div>
                        <h1 class="text-lg font-extrabold text-slate-900">تفعيل جهاز الكاشير للعمل أوفلاين</h1>
                        <p class="text-xs text-gray-500">{{ $store->name }} — اربط هذا الجهاز ليعمل حتى عند انقطاع الإنترنت</p>
                    </div>

                    <div x-show="activationError"
                         class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold"
                         x-text="activationError"></div>

                    <form @submit.prevent="activateTerminal()" class="space-y-4 text-xs">
                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">البريد الإلكتروني أو رقم الجوال</label>
                            <input type="text" x-model="activationForm.login" required
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">رمز جهاز الكاشير (Terminal Code)</label>
                            @if ($terminals->count() > 0)
                                <select x-model="activationForm.terminal_code"
                                        class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                                    @foreach ($terminals as $t)
                                        <option value="{{ $t->code }}">{{ $t->code }} — {{ $t->name }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" x-model="activationForm.terminal_code" placeholder="T01" required
                                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm font-mono text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">
                                كلمة المرور <span class="text-rose-600">*</span>
                            </label>
                            <input type="password" x-model="activationForm.password" required autofocus
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">رمز القفل السريع PIN (4 أرقام - اختياري)</label>
                            <input type="password" inputmode="numeric" maxlength="4" x-model="activationForm.quick_pin" placeholder="مثال: 1234"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm font-mono tabular-nums text-slate-900 placeholder:font-sans focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <button type="submit" :disabled="activationLoading"
                                class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-bold shadow-xs transition-colors cursor-pointer">
                            <svg x-show="!activationLoading" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                            </svg>
                            <svg x-show="activationLoading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <span x-show="!activationLoading">تفعيل الجهاز وتحميل الأصناف</span>
                            <span x-show="activationLoading">جاري التفعيل والمزامنة...</span>
                        </button>
                    </form>

                    <div class="text-center pt-4 border-t border-gray-100">
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-slate-900 transition-colors">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 19-7-7 7-7" />
                                <path d="M19 12H5" />
                            </svg>
                            <span>العودة إلى لوحة الإدارة</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Cashier PIN Lock Overlay (never clears cart) -->
    <div x-show="isLocked" x-transition.opacity style="display: none;"
         class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 text-center space-y-5 shadow-2xl">
            <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-600 mx-auto flex items-center justify-center">
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>

            <div class="space-y-1">
                <h2 class="text-base font-extrabold text-slate-900">الشاشة مقفلة مؤقتاً</h2>
                <p class="text-xs text-gray-500 tabular-nums">السلة الحالية محفوظة بأمان (<span class="font-bold text-slate-700" x-text="cart.length"></span> صنف)</p>
            </div>

            <div x-show="lockError" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold" x-text="lockError"></div>

            <form x-show="!unlockPasswordMode" @submit.prevent="unlockWithPin()" class="space-y-3">
                <input type="password" inputmode="numeric" maxlength="4" x-model="lockPinInput" placeholder="أدخل رمز PIN (4 أرقام)" autofocus
                       class="w-full text-center text-2xl tracking-[0.6em] font-mono font-bold tabular-nums rounded-xl border border-gray-200 bg-gray-50/70 py-3 placeholder:text-xs placeholder:tracking-normal placeholder:font-sans focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                    فتح الشاشة
                </button>
            </form>

            <form x-show="unlockPasswordMode" @submit.prevent="unlockWithPasswordOnline()" class="space-y-3">
                <input type="password" x-model="unlockPasswordInput" placeholder="كلمة المرور (يتطلب إنترنت)"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm transition-colors cursor-pointer">
                    تحقق وفتح
                </button>
            </form>

            <button type="button" @click="unlockPasswordMode = !unlockPasswordMode; lockError = ''"
                    class="text-xs font-semibold text-gray-500 hover:text-slate-900 underline cursor-pointer">
                <span x-text="unlockPasswordMode ? 'العودة لرمز PIN' : 'نسيت PIN؟ فتح بكلمة المرور'"></span>
            </button>
        </div>
    </div>

    <!-- Main POS Workspace -->
    <div x-show="initialized && terminalToken" style="display: none;" class="min-h-screen lg:h-screen flex flex-col">

        <!-- Top Bar -->
        <header class="bg-slate-900 text-white px-3.5 lg:px-5 py-2.5 flex flex-wrap items-center justify-between gap-2.5 border-b border-slate-800 shrink-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex items-center gap-2.5">
                    <span class="px-2.5 py-1 rounded-xl bg-emerald-600 text-white text-xs font-mono font-bold tabular-nums shadow-xs" x-text="terminalCode || 'T01'"></span>
                    <div>
                        <div class="font-bold text-sm leading-tight text-white" x-text="storeSettings.name || '{{ $store->name }}'"></div>
                        <div class="text-[11px] text-slate-400">الكاشير: <span class="text-slate-200 font-medium" x-text="userInfo?.name || '{{ $user->name }}'"></span></div>
                    </div>
                </div>

                <!-- Connection Pill -->
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold flex items-center gap-1.5 border"
                      :class="isOnline ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/35' : 'bg-rose-500/15 text-rose-300 border-rose-500/35'">
                    <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400' : 'bg-rose-400 animate-pulse'"></span>
                    <span x-text="isOnline ? 'متصل' : 'أوفلاين'"></span>
                </span>

                <!-- Outbox Counter & Sync Status -->
                <button type="button" @click="openOutboxModal()"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors flex items-center gap-1.5 cursor-pointer tabular-nums"
                        :class="outboxCount > 0 ? 'bg-amber-500/15 text-amber-300 border-amber-500/35 hover:bg-amber-500/25' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700'">
                    <svg class="w-3.5 h-3.5 opacity-80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="17 8 12 3 7 8" />
                        <line x1="12" y1="3" x2="12" y2="15" />
                    </svg>
                    <span>طابور المزامنة:</span>
                    <span x-text="outboxCount"></span>
                    <span x-show="syncErrors.length > 0" class="text-rose-400 font-extrabold">(!)</span>
                </button>

                <!-- Shift Status Pill -->
                <button type="button" @click="openShiftModal()"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors flex items-center gap-1.5 cursor-pointer tabular-nums"
                        :class="activeShift ? 'bg-sky-500/15 text-sky-300 border-sky-500/35 hover:bg-sky-500/25' : 'bg-rose-500/15 text-rose-300 border-rose-500/35 hover:bg-rose-500/25'">
                    <svg x-show="activeShift" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    <svg x-show="!activeShift" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                        <path d="M12 9v4" />
                        <path d="M12 17h.01" />
                    </svg>
                    <span x-show="activeShift">الوردية مفتوحة • الدرج: <strong x-text="fmtMoney(activeShift?.expected_cash || 0)"></strong> <span x-text="currencySymbol()"></span></span>
                    <span x-show="!activeShift">افتح الوردية</span>
                </button>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap text-xs">
                <button type="button" @click="syncNow(true)" :disabled="isSyncing"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-bold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" :class="isSyncing ? 'animate-spin' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                        <path d="M3 3v5h5" />
                        <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
                        <path d="M16 16h5v5" />
                    </svg>
                    <span x-text="isSyncing ? 'جاري المزامنة...' : 'مزامنة الآن'"></span>
                </button>

                <button type="button" @click="openDrawerModal()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 font-semibold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2" />
                        <circle cx="12" cy="12" r="2" />
                        <path d="M6 12h.01M18 12h.01" />
                    </svg>
                    <span>حركات الدرج</span>
                </button>

                <button type="button" @click="openHeldCartsModal()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 font-semibold transition-colors cursor-pointer tabular-nums">
                    <svg class="w-3.5 h-3.5 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="4" width="4" height="16" rx="1" />
                        <rect x="14" y="4" width="4" height="16" rx="1" />
                    </svg>
                    <span>معلق (<span x-text="heldCarts.length"></span>)</span>
                    <span class="text-slate-400 text-[10px] font-mono">F4</span>
                </button>

                <button type="button" @click="openBackupModal()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 font-semibold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    <span>نسخة طوارئ</span>
                </button>

                <button type="button" @click="reprintLastReceipt()" x-show="lastReceiptSnapshot" style="display:none;"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 font-semibold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9V2h12v7" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    <span>إعادة طباعة</span>
                </button>

                <button type="button" @click="openLastSaleWhatsApp()" x-show="lastSaleSnapshot" style="display:none;"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 border border-emerald-600 text-white font-semibold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                    </svg>
                    <span>واتساب</span>
                </button>

                <button type="button" @click="openCashDrawerHardware()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 font-semibold transition-colors cursor-pointer"
                        title="فتح درج النقدية عبر WebUSB أو عرض دليل الطباعة الصامتة">
                    <svg class="w-3.5 h-3.5 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z" />
                    </svg>
                    <span>درج USB</span>
                </button>

                <button type="button" @click="openOrTogglePinLock()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition-colors cursor-pointer">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <span>قفل PIN</span>
                </button>

                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                    </svg>
                    <span>الإدارة</span>
                </a>
            </div>
        </header>

        <!-- Token Revoked Alert -->
        <div x-show="authInvalidWarning" style="display: none;"
             class="bg-rose-600 text-white px-4 py-2.5 text-xs font-bold flex flex-wrap items-center justify-between gap-2 shrink-0">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                </svg>
                <span>انتهت جلسة هذا الجهاز أو تم إيقافه من الإدارة. يمكنك تصدير نسخة طوارئ مشفرة قبل إعادة التفعيل.</span>
            </div>
            <button type="button" @click="deactivateTerminalPrompt()"
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-rose-50 text-rose-700 font-bold transition-colors cursor-pointer">
                إعادة تفعيل الجهاز
            </button>
        </div>

        <!-- Split Workspace: Right = Catalog / Search / Quick Pick, Left = Active Cart & Checkout -->
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-4 p-3.5 lg:p-4 overflow-hidden min-h-0">

            <!-- Catalog, Barcode & Quick Pick Column -->
            <section class="lg:col-span-6 xl:col-span-7 flex flex-col gap-3.5 min-h-0">

                <!-- Auto-Focus Barcode + Instant Search -->
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-3.5 grid grid-cols-1 sm:grid-cols-2 gap-3 shrink-0">
                    <div>
                        <label class="flex items-center gap-1.5 text-[11px] font-bold text-emerald-700 mb-1.5">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14" />
                            </svg>
                            <span>مسح الباركود (تركيز دائم) + Enter</span>
                        </label>
                        <input type="text"
                               x-ref="barcodeInput"
                               x-model="barcodeInput"
                               @keydown.enter.prevent="handleBarcodeSubmit()"
                               placeholder="امسح باركود الصنف / الميزان / الوحدة..."
                               class="w-full rounded-xl border-2 border-emerald-600 bg-emerald-50/40 px-3.5 py-2.5 text-sm font-bold text-slate-900 placeholder:font-normal placeholder:text-gray-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-emerald-600/20 transition-colors">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="flex items-center gap-1.5 text-[11px] font-bold text-slate-600">
                                <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8" />
                                    <path d="m21 21-4.3-4.3" />
                                </svg>
                                <span>بحث فوري بالاسم أو الكود</span>
                            </label>
                            <span x-show="searchQuery" class="text-[10px] text-emerald-700 font-bold tabular-nums" x-text="`${searchResults.length} نتيجة`"></span>
                        </div>
                        <div class="relative">
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="ابحث بالاسم، SKU، أو الباركود..."
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 pl-8 pr-3.5 py-2.5 text-sm text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10 focus:outline-none transition-colors">
                            <button x-show="searchQuery" @click="searchQuery = ''; focusBarcodeInput()" type="button"
                                    class="absolute left-2.5 top-1/2 -translate-y-1/2 p-1 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Categories Filter Bar -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 shrink-0">
                    <button type="button" @click="selectedCategoryId = null"
                            :class="selectedCategoryId === null ? 'bg-slate-900 text-white border-slate-900 shadow-xs' : 'bg-white text-slate-700 border-gray-200 hover:bg-gray-50'"
                            class="px-3.5 py-2 rounded-xl border text-xs font-bold shrink-0 transition-colors cursor-pointer tabular-nums">
                        الكل (<span x-text="products.length"></span>)
                    </button>
                    <template x-for="cat in categories" :key="cat.id">
                        <button type="button" @click="selectedCategoryId = cat.id"
                                :class="Number(selectedCategoryId) === Number(cat.id) ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white text-slate-700 border-gray-200 hover:bg-gray-50'"
                                class="px-3.5 py-2 rounded-xl border text-xs font-bold shrink-0 transition-colors cursor-pointer"
                                x-text="cat.name"></button>
                    </template>
                </div>

                <!-- Instant Search Results (max 20) OR Quick-Pick Grid -->
                <div class="flex-1 rounded-2xl border border-gray-200 bg-white shadow-xs p-4 overflow-y-auto space-y-5 min-h-[280px]">

                    <!-- Search Results -->
                    <div x-show="searchQuery || selectedCategoryId">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-xs font-bold text-slate-800">نتائج البحث السريع (بحد أقصى 20)</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 tabular-nums" x-text="`${searchResults.length} صنف`"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5">
                            <template x-for="prod in searchResults" :key="prod.id">
                                <button type="button" @click="selectProductFromGrid(prod)"
                                        class="text-right p-3 rounded-xl border border-gray-200 bg-white hover:border-emerald-600 hover:bg-emerald-50/40 transition-all flex flex-col justify-between gap-2 cursor-pointer">
                                    <div class="font-bold text-xs text-slate-900 line-clamp-2 leading-snug" x-text="prod.name"></div>
                                    <div class="flex items-center justify-between text-[11px] pt-2 border-t border-gray-100 tabular-nums">
                                        <span class="font-extrabold text-emerald-700" x-text="`${fmtMoney(prod.sale_price)} ${currencySymbol()}`"></span>
                                        <span class="text-gray-400 font-medium" x-text="`م: ${fmtQty(prod.quantity)}`"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                        <div x-show="searchResults.length === 0" class="text-center py-8 text-xs text-gray-400">
                            لا توجد أصناف مطابقة للبحث.
                        </div>
                    </div>

                    <!-- Quick Pick Grid (No-Barcode Items) -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                                </svg>
                                <h3 class="text-xs font-bold text-slate-900">أصناف البيع السريع (بدون باركود)</h3>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200/70 tabular-nums" x-text="`${quickPickProducts.length} صنف`"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5">
                            <template x-for="qp in quickPickProducts" :key="qp.id">
                                <button type="button" @click="selectProductFromGrid(qp)"
                                        class="p-3 rounded-xl bg-amber-50/60 hover:bg-amber-100/80 border border-amber-200/80 text-right transition-all flex flex-col justify-between gap-2 cursor-pointer">
                                    <div class="font-bold text-xs text-slate-900 leading-snug" x-text="qp.name"></div>
                                    <div class="flex items-center justify-between text-xs font-extrabold text-amber-900 pt-1.5 border-t border-amber-200/60 tabular-nums">
                                        <span x-text="`${fmtMoney(qp.sale_price)} ${currencySymbol()}`"></span>
                                        <span class="text-[10px] font-bold text-amber-700">+ إضافة</span>
                                    </div>
                                </button>
                            </template>
                        </div>
                        <div x-show="quickPickProducts.length === 0 && !searchQuery && !selectedCategoryId" class="text-center py-12 space-y-2">
                            <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto flex items-center justify-center">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14" />
                                </svg>
                            </div>
                            <p class="text-xs text-gray-500">امسح باركود الصنف أو ابحث بالاسم، أو فعّل خيار "بيع سريع" لأي صنف من لوحة المنتجات.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Active Cart & Checkout Column -->
            <section class="lg:col-span-6 xl:col-span-5 rounded-2xl border border-gray-200 bg-white shadow-xs flex flex-col min-h-0 overflow-hidden">

                <!-- Cart Header & Selected Customer -->
                <div class="px-4 py-3.5 border-b border-gray-100 flex items-center justify-between gap-2 bg-[#F9FAFB] shrink-0">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-sm text-slate-900">الفاتورة الحالية</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-900 text-white tabular-nums" x-text="cart.length"></span>
                        </div>
                        <div class="text-[11px] text-gray-500 mt-0.5 truncate">
                            العميل:
                            <strong class="text-slate-800" x-text="selectedCustomer ? selectedCustomer.name : 'عميل نقدي'"></strong>
                            <button x-show="selectedCustomer" @click="selectedCustomer = null" type="button" class="text-rose-600 hover:underline mr-1 text-[10px] font-semibold cursor-pointer">(إلغاء)</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" @click="openPaymentModal()"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-800 text-xs font-bold shadow-xs transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            <span>عميل / دفع</span>
                            <span class="text-[10px] font-mono text-gray-400">F8</span>
                        </button>
                    </div>
                </div>

                <!-- Cost Override Warning -->
                <div x-show="cartComputation.requiresCostOverride" style="display: none;"
                     class="px-4 py-2.5 bg-amber-50 border-b border-amber-200 text-amber-900 text-xs font-bold flex items-center justify-between gap-2 shrink-0">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <path d="M12 9v4" />
                            <path d="M12 17h.01" />
                        </svg>
                        <span>تنبيه: السعر بعد الخصم أقل من التكلفة المسموحة.</span>
                    </div>
                    <span x-show="!canOverrideDiscounts()" class="text-[10px] bg-amber-200/80 text-amber-950 px-2 py-0.5 rounded-md shrink-0">يتطلب رمز المدير</span>
                </div>

                <!-- Cart Lines -->
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100 min-h-[220px]">
                    <template x-for="(line, idx) in cart" :key="line.lineKey">
                        <div @click="selectedLineIndex = idx"
                             :class="selectedLineIndex === idx ? 'bg-emerald-50/60' : 'hover:bg-gray-50/80'"
                             class="px-4 py-3 transition-colors cursor-pointer">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-slate-900 truncate" x-text="line.product_name"></div>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap text-[11px] text-gray-500">
                                        <span class="tabular-nums">السعر: <strong class="text-slate-700" x-text="fmtMoney(line.unit_price)"></strong></span>

                                        <!-- Unit switcher if multi-units exist -->
                                        <template x-if="(line.available_units || []).length > 0">
                                            <select @change="changeLineUnit(idx, $event.target.value)"
                                                    class="border border-gray-200 rounded-lg px-2 py-0.5 text-[11px] bg-white text-slate-700 focus:border-emerald-600 focus:outline-none">
                                                <template x-if="!line.available_units.some(u => Boolean(u.is_default))">
                                                    <option value="" :selected="!line.unit_id">حبة (أساسي)</option>
                                                </template>
                                                <template x-for="u in line.available_units" :key="u.id">
                                                    <option :value="u.id" :selected="Number(u.id) === Number(line.unit_id)" x-text="`${u.unit_name} (×${fmtQty(u.conversion_factor)})`"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="(line.available_units || []).length === 0">
                                            <span class="px-2 py-0.5 rounded-md bg-gray-100 text-slate-600 font-medium" x-text="line.unit_name_snapshot"></span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Line Net Total & Remove -->
                                <div class="text-left shrink-0 flex items-center gap-2">
                                    <div class="font-extrabold text-sm text-emerald-700 tabular-nums"
                                         x-text="fmtMoney(cartComputation.lines[idx]?.lineNet || 0)"></div>
                                    <button type="button" @click.stop="removeCartItem(idx)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer" title="حذف الصنف">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18" />
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Quantity Controls & Item Discount -->
                            <div class="mt-2.5 flex items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-1">
                                    <button type="button" @click.stop="adjustLineQty(idx, -1)"
                                            class="w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-slate-700 font-bold flex items-center justify-center transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                            <path d="M5 12h14" />
                                        </svg>
                                    </button>
                                    <input type="number" step="any" min="0.001" x-model="line.quantity"
                                           @blur="line.quantity = formatInputQty(line.quantity || '1')"
                                           @change="line.quantity = formatInputQty(line.quantity || '1')"
                                           @click.stop
                                           class="w-20 text-center rounded-lg border border-gray-200 py-1 font-bold text-xs text-slate-900 bg-white tabular-nums focus:border-emerald-600 focus:outline-none">
                                    <button type="button" @click.stop="adjustLineQty(idx, 1)"
                                            class="w-7 h-7 rounded-lg border border-gray-200 bg-white hover:bg-gray-100 text-slate-700 font-bold flex items-center justify-center transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                            <path d="M12 5v14M5 12h14" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <span class="text-[11px] text-gray-500">خصم الصنف:</span>
                                    <input type="number" step="any" min="0" x-model="line.discount_amount"
                                           @blur="line.discount_amount = formatInputMoney(line.discount_amount || '0')"
                                           @change="line.discount_amount = formatInputMoney(line.discount_amount || '0')"
                                           @click.stop
                                           class="w-20 text-center rounded-lg border border-gray-200 py-1 text-xs font-semibold text-slate-900 bg-white tabular-nums focus:border-emerald-600 focus:outline-none">
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="cart.length === 0" class="py-16 px-4 text-center space-y-2.5">
                        <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 mx-auto flex items-center justify-center">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="9" cy="21" r="1" />
                                <circle cx="20" cy="21" r="1" />
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                            </svg>
                        </div>
                        <div class="text-xs font-semibold text-slate-600">السلة فارغة — امسح باركود أو اختر صنفاً للبدء</div>
                        <div class="text-[11px] text-gray-400 font-mono">F2 دفع نقدي سريع • F4 تعليق • F8 دفع متعدد • +/- كمية</div>
                    </div>
                </div>

                <!-- Totals & Primary Action Buttons -->
                <div class="p-4 border-t border-gray-200 bg-[#F9FAFB] space-y-3 shrink-0">
                    <div class="grid grid-cols-2 gap-2.5 text-xs">
                        <div class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-gray-200">
                            <span class="text-gray-500">المجموع:</span>
                            <strong class="text-slate-900 tabular-nums" x-text="fmtMoney(cartComputation.subtotal)"></strong>
                        </div>
                        <div class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border border-gray-200">
                            <span class="text-gray-500">خصم إضافي:</span>
                            <input type="number" min="0" step="any" x-model="orderDiscountAmount" @change="orderDiscountAmount = formatInputMoney(orderDiscountAmount)"
                                   class="w-20 text-left font-bold text-slate-900 tabular-nums border-b border-gray-300 focus:border-emerald-600 focus:outline-none text-xs">
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-1">
                        <div class="text-xs text-gray-500 tabular-nums">
                            <span>ضريبة: <strong class="text-slate-700" x-text="fmtMoney(cartComputation.taxTotal)"></strong></span>
                        </div>
                        <div class="text-left tabular-nums">
                            <span class="text-xs text-gray-500 ml-1.5">الصافي للدفع:</span>
                            <span class="text-2xl font-extrabold text-slate-900" x-text="fmtMoney(cartComputation.totalAmount)"></span>
                            <span class="text-xs font-bold text-gray-500" x-text="currencySymbol()"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-2.5">
                        <button type="button" @click="quickCashCheckout()" :disabled="cart.length === 0"
                                class="col-span-7 inline-flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="6" width="20" height="12" rx="2" />
                                <circle cx="12" cy="12" r="2" />
                                <path d="M6 12h.01M18 12h.01" />
                            </svg>
                            <span>دفع نقدي سريع (F2)</span>
                        </button>
                        <button type="button" @click="openPaymentModal()" :disabled="cart.length === 0"
                                class="col-span-5 inline-flex items-center justify-center gap-2 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="5" width="20" height="14" rx="2" />
                                <line x1="2" y1="10" x2="22" y2="10" />
                            </svg>
                            <span>متعدد / آجل (F8)</span>
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- MODAL BACKDROP -->
    <div x-show="modal !== null" x-transition.opacity style="display: none;"
         class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4"
         @click.self="closeModal()">

        <!-- Variant Picker Modal -->
        <div x-show="modal === 'variant_picker'" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="font-bold text-sm text-slate-900 truncate" x-text="variantPickerData.product?.name || 'اختر المقاس / اللون'"></h3>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-5 grid grid-cols-2 gap-2.5 max-h-80 overflow-y-auto">
                <template x-for="v in variantPickerData.variants" :key="v.id">
                    <button type="button" @click="pickVariantForCart(v)"
                            class="p-3 rounded-xl border border-gray-200 hover:border-emerald-600 hover:bg-emerald-50/50 text-right transition-all cursor-pointer">
                        <div class="font-bold text-xs text-slate-900" x-text="v.attribute_label || `${v.size} - ${v.color}`"></div>
                        <div class="flex justify-between text-[11px] mt-1.5 pt-1.5 border-t border-gray-100 tabular-nums">
                            <span class="font-bold text-emerald-700" x-text="`${fmtMoney(v.sale_price)} ${currencySymbol()}`"></span>
                            <span class="text-gray-400" x-text="`م: ${fmtQty(v.quantity)}`"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Shift Open / Close Modal -->
        <div x-show="modal === 'shift'" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="font-bold text-sm text-slate-900" x-text="activeShift ? 'إغلاق الوردية (تقرير Z)' : 'فتح وردية كاشير جديدة'"></h3>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5">
                <template x-if="!activeShift">
                    <form @submit.prevent="openShiftLocal()" class="space-y-4 text-xs">
                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">النقد الافتتاحي بالدرج (<span x-text="currencySymbol()"></span>)</label>
                            <input type="number" step="any" min="0" x-model="shiftOpeningCash" @change="shiftOpeningCash = formatInputMoney(shiftOpeningCash)" required autofocus
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 font-bold text-base text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">ملاحظات (اختياري)</label>
                            <input type="text" x-model="shiftNotes"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                            فتح الوردية الآن
                        </button>
                    </form>
                </template>

                <template x-if="activeShift">
                    <form @submit.prevent="closeShiftLocal()" class="space-y-4 text-xs">
                        <div class="bg-[#F9FAFB] rounded-xl p-3.5 space-y-2 border border-gray-200 tabular-nums">
                            <div class="flex justify-between text-gray-600"><span>الافتتاح:</span><strong class="text-slate-900" x-text="fmtMoney(activeShift.opening_cash)"></strong></div>
                            <div class="flex justify-between text-emerald-700"><span>المبيعات النقدية:</span><strong x-text="fmtMoney(activeShift.cash_sales_total)"></strong></div>
                            <div class="flex justify-between text-sky-700"><span>سندات قبض نقدية:</span><strong x-text="fmtMoney(activeShift.cash_in_total)"></strong></div>
                            <div class="flex justify-between text-rose-700"><span>مصروفات وسحوبات الدرج:</span><strong x-text="fmtMoney(activeShift.cash_out_total)"></strong></div>
                            <div class="flex justify-between pt-2.5 border-t border-gray-200 font-extrabold text-sm text-slate-900">
                                <span>النقد المتوقع بالدرج:</span>
                                <span x-text="`${fmtMoney(activeShift.expected_cash)} ${currencySymbol()}`"></span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">
                                النقد الفعلي المعدود في الدرج <span class="text-rose-600">*</span>
                            </label>
                            <input type="number" step="any" min="0" x-model="shiftClosingActualCash" @change="if (shiftClosingActualCash !== '') shiftClosingActualCash = formatInputMoney(shiftClosingActualCash)" required
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 font-bold text-base text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">ملاحظات الإغلاق</label>
                            <input type="text" x-model="shiftNotes"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                            إغلاق الوردية وطباعة Z-Report
                        </button>
                    </form>
                </template>
            </div>
        </div>

        <!-- Yemeni Payment Modal (F8) -->
        <div x-show="modal === 'payment'" class="w-full max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-2xl max-h-[92vh] overflow-y-auto">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 sticky top-0 bg-white z-10">
                <div>
                    <h3 class="font-bold text-base text-slate-900">نافذة الدفع اليمنية</h3>
                    <div class="text-xs text-gray-500 mt-0.5 tabular-nums">
                        إجمالي الفاتورة:
                        <strong class="text-lg font-extrabold text-slate-900" x-text="`${fmtMoney(cartComputation.totalAmount)} ${currencySymbol()}`"></strong>
                    </div>
                </div>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <!-- Customer Selector & Offline Customer Creation -->
                <div class="bg-[#F9FAFB] rounded-xl p-3.5 border border-gray-200 space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold text-slate-700">
                            العميل المختار: <span class="text-emerald-700" x-text="selectedCustomer ? `${selectedCustomer.name} (${selectedCustomer.phone || '-'})` : 'عميل نقدي'"></span>
                        </span>
                        <button type="button" @click="showNewCustomerForm = !showNewCustomerForm"
                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition-colors cursor-pointer">
                            <span x-text="showNewCustomerForm ? 'إلغاء' : '+ عميل آجل جديد أوفلاين'"></span>
                        </button>
                    </div>

                    <div x-show="!showNewCustomerForm" class="space-y-2">
                        <input type="text" x-model="customerSearchQuery" placeholder="ابحث عن عميل بالاسم أو الهاتف..."
                               class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs bg-white focus:border-emerald-600 focus:outline-none">
                        <div class="flex gap-1.5 overflow-x-auto pb-1">
                            <button type="button" @click="selectedCustomer = null"
                                    :class="!selectedCustomer ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-700 border-gray-200 hover:bg-gray-50'"
                                    class="px-3 py-1.5 rounded-lg border text-[11px] font-semibold shrink-0 transition-colors cursor-pointer">نقدي (عام)</button>
                            <template x-for="cust in filteredPaymentCustomers" :key="`${cust.id}-${cust.client_uuid}`">
                                <button type="button" @click="selectedCustomer = cust"
                                        :class="selectedCustomer && ((selectedCustomer.client_uuid && selectedCustomer.client_uuid === cust.client_uuid) || Number(selectedCustomer.id) === Number(cust.id)) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-gray-200 hover:bg-gray-50'"
                                        class="px-3 py-1.5 rounded-lg border text-[11px] font-semibold shrink-0 transition-colors cursor-pointer tabular-nums">
                                    <span x-text="cust.name"></span>
                                    <span class="opacity-75" x-text="`(${fmtMoney(cust.current_balance)})`"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Inline Offline Customer Form -->
                    <div x-show="showNewCustomerForm" class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2.5 border-t border-gray-200 text-xs">
                        <input type="text" x-model="newCustomerForm.name" placeholder="اسم العميل *" class="rounded-xl border border-gray-200 px-3 py-2 bg-white focus:border-emerald-600 focus:outline-none">
                        <input type="text" x-model="newCustomerForm.phone" placeholder="رقم الجوال" class="rounded-xl border border-gray-200 px-3 py-2 bg-white font-mono tabular-nums placeholder:font-sans focus:border-emerald-600 focus:outline-none">
                        <div class="flex gap-1.5">
                            <input type="number" min="0" x-model="newCustomerForm.credit_limit" placeholder="سقف الدين" class="w-full rounded-xl border border-gray-200 px-3 py-2 bg-white tabular-nums focus:border-emerald-600 focus:outline-none">
                            <button type="button" @click="createOfflineCustomer()" class="px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold shrink-0 transition-colors cursor-pointer">حفظ</button>
                        </div>
                    </div>
                </div>

                <!-- Quick Yemeni Banknotes -->
                <div class="space-y-1.5">
                    <div class="text-[11px] font-bold text-gray-500">فئات النقد اليمنية السريعة:</div>
                    <div class="flex flex-wrap gap-2 tabular-nums">
                        <button type="button" @click="applyBanknote(1000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-xs font-bold transition-colors cursor-pointer">+1,000</button>
                        <button type="button" @click="applyBanknote(5000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-xs font-bold transition-colors cursor-pointer">+5,000</button>
                        <button type="button" @click="applyBanknote(10000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-xs font-bold transition-colors cursor-pointer">+10,000</button>
                        <button type="button" @click="applyBanknote(20000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-xs font-bold transition-colors cursor-pointer">+20,000</button>
                        <button type="button" @click="setExactCash()" class="px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer">بالضبط</button>
                        <button type="button" @click="clearCashPayment()" class="px-3 py-1.5 rounded-xl border border-gray-200 bg-gray-100 hover:bg-gray-200 text-slate-700 text-xs font-bold transition-colors cursor-pointer">تصفير النقد</button>
                    </div>
                </div>

                <!-- Payment Rows (Cash + Yemeni Wallets) -->
                <div class="space-y-2.5">
                    <template x-for="(row, rIdx) in paymentRows" :key="rIdx">
                        <div class="grid grid-cols-12 gap-2 items-center text-xs">
                            <select x-model="row.method" @change="row.untouchedDefault = false"
                                    class="col-span-4 rounded-xl border border-gray-200 bg-gray-50/70 px-2.5 py-2 text-slate-900 font-semibold focus:bg-white focus:border-emerald-600 focus:outline-none">
                                <option value="cash">نقدي</option>
                                <option value="kuraimi">الكريمي</option>
                                <option value="jawali">جوالي</option>
                                <option value="floosak">فلوسك</option>
                                <option value="onecash">ون كاش</option>
                                <option value="card">بطاقة</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                            </select>
                            <input type="number" step="any" min="0" x-model="row.amount" @input="row.untouchedDefault = false" @change="row.amount = formatInputMoney(row.amount)"
                                   placeholder="المبلغ" class="col-span-4 rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 font-bold text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:outline-none">
                            <input type="text" x-model="row.reference_number" placeholder="رقم المرجع (اختياري)"
                                   class="col-span-3 rounded-xl border border-gray-200 bg-gray-50/70 px-2.5 py-2 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                            <button type="button" @click="removePaymentRow(rIdx)"
                                    class="col-span-1 p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition-colors cursor-pointer">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 6 6 18M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>

                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <button type="button" @click="addPaymentRow('kuraimi')" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-slate-700 text-[11px] font-bold transition-colors cursor-pointer">+ الكريمي</button>
                        <button type="button" @click="addPaymentRow('jawali')" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-slate-700 text-[11px] font-bold transition-colors cursor-pointer">+ جوالي</button>
                        <button type="button" @click="addPaymentRow('floosak')" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-slate-700 text-[11px] font-bold transition-colors cursor-pointer">+ فلوسك</button>
                        <button type="button" @click="addPaymentRow('onecash')" class="px-2.5 py-1 rounded-lg border border-gray-200 bg-gray-50 hover:bg-gray-100 text-slate-700 text-[11px] font-bold transition-colors cursor-pointer">+ ون كاش</button>
                    </div>
                </div>

                <!-- Credit Amount + Change Display -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-gray-100">
                    <div class="bg-amber-50/60 border border-amber-200/80 rounded-xl p-3.5 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <label class="font-bold text-amber-900">مبلغ الآجل (على الحساب)</label>
                            <button type="button" @click="setRemainingAsCredit()" class="text-[11px] text-amber-800 underline font-bold cursor-pointer">المتبقي آجل</button>
                        </div>
                        <input type="number" step="any" min="0" x-model="creditAmountInput" @change="creditAmountInput = formatInputMoney(creditAmountInput)"
                               class="w-full rounded-xl border border-amber-300 px-3 py-2 font-bold text-sm text-slate-900 tabular-nums bg-white focus:border-amber-600 focus:outline-none">
                    </div>

                    <div class="bg-emerald-50 border border-emerald-200/80 rounded-xl p-3.5 flex flex-col justify-between">
                        <div class="text-xs font-bold text-emerald-800">الباقي للعميل (Change)</div>
                        <div class="text-3xl font-extrabold text-emerald-700 tracking-tight tabular-nums">
                            <span x-text="fmtMoney(paymentSummary.changeAmount)"></span>
                            <span class="text-sm font-bold" x-text="currencySymbol()"></span>
                        </div>
                    </div>
                </div>

                <!-- Manager Override PIN if needed -->
                <div x-show="cartComputation.requiresCostOverride && !canOverrideDiscounts()"
                     class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs flex items-center justify-between gap-3">
                    <span class="font-bold text-rose-800">مطلوب رمز موافقة المدير (البيع أقل من التكلفة):</span>
                    <input type="password" inputmode="numeric" maxlength="4" x-model="paymentOverridePin" placeholder="4 أرقام"
                           class="w-28 text-center rounded-xl border border-rose-300 py-1.5 font-mono font-bold tabular-nums bg-white focus:outline-none">
                </div>

                <div x-show="!paymentSummary.isBalanced" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold"
                     x-text="paymentSummary.errors[0] || 'تحقق من توازن المدفوع + الآجل'"></div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-gray-100">
                    <div class="text-xs text-gray-600 tabular-nums">
                        صافي المدفوع: <strong class="text-slate-900" x-text="fmtMoney(paymentSummary.paidAmount)"></strong> + آجل: <strong class="text-amber-800" x-text="fmtMoney(paymentSummary.creditAmount)"></strong>
                    </div>
                    <button type="button" @click="submitPaymentModal()" :disabled="!paymentSummary.isBalanced"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        <span>تأكيد وإصدار الفاتورة</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Held Carts Modal (F4) -->
        <div x-show="modal === 'held'" class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="font-bold text-sm text-slate-900">السلات المعلقة (F4)</h3>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <button x-show="cart.length > 0" type="button" @click="holdCurrentCart()"
                        class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition-colors cursor-pointer tabular-nums">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="4" width="4" height="16" rx="1" />
                        <rect x="14" y="4" width="4" height="16" rx="1" />
                    </svg>
                    <span>تعليق الفاتورة الحالية (<span x-text="fmtMoney(cartComputation.totalAmount)"></span> <span x-text="currencySymbol()"></span>)</span>
                </button>

                <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto text-xs">
                    <template x-for="h in heldCarts" :key="h.id">
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate" x-text="h.customer_name || 'عميل نقدي'"></div>
                                <div class="text-gray-500 tabular-nums mt-0.5" x-text="`${(h.items || []).length} أصناف • ${fmtMoney(h.total_amount)} ${currencySymbol()}`"></div>
                            </div>
                            <div class="flex gap-1.5 shrink-0">
                                <button type="button" @click="restoreHeldCart(h)" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition-colors cursor-pointer">استرجاع</button>
                                <button type="button" @click="deleteHeldCart(h.id)" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200/80 font-bold transition-colors cursor-pointer">حذف</button>
                            </div>
                        </div>
                    </template>
                    <div x-show="heldCarts.length === 0" class="py-8 text-center text-gray-400">لا توجد سلات معلقة.</div>
                </div>
            </div>
        </div>

        <!-- Quick Drawer Movements Modal (Offline) -->
        <div x-show="modal === 'drawer'" class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="font-bold text-sm text-slate-900">حركات الدرج السريعة (أوفلاين)</h3>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-4 gap-1.5 text-[11px] font-bold">
                    <button type="button" @click="drawerTab = 'expense'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'expense' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-slate-700 hover:bg-gray-200'" class="py-2 rounded-xl transition-colors cursor-pointer">مصروف نثري</button>
                    <button type="button" @click="drawerTab = 'employee_advance'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'employee_advance' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-slate-700 hover:bg-gray-200'" class="py-2 rounded-xl transition-colors cursor-pointer">سلفة عامل</button>
                    <button type="button" @click="drawerTab = 'supplier_payment'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'supplier_payment' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-slate-700 hover:bg-gray-200'" class="py-2 rounded-xl transition-colors cursor-pointer">دفعة مورد</button>
                    <button type="button" @click="drawerTab = 'customer_settlement'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'customer_settlement' ? 'bg-slate-900 text-white' : 'bg-gray-100 text-slate-700 hover:bg-gray-200'" class="py-2 rounded-xl transition-colors cursor-pointer">قبض عميل</button>
                </div>

                <form @submit.prevent="submitDrawerMovement()" class="space-y-3.5 text-xs">
                    <div x-show="drawerTab === 'expense'" class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">بند المصروف</label>
                        <select x-model="drawerForm.category" class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                            <template x-for="cat in drawerCategories" :key="cat">
                                <option :value="cat" x-text="cat"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="drawerTab === 'employee_advance'" class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">اختر العامل / الموظف</label>
                        <select x-model="drawerForm.payee_id" class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                            <option value="">— اختر العامل —</option>
                            <template x-for="emp in employeeQuickPayees" :key="emp.id">
                                <option :value="emp.id" x-text="emp.name"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="drawerTab === 'supplier_payment'" class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">اختر المورد</label>
                        <select x-model="drawerForm.payee_id" class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                            <option value="">— اختر المورد —</option>
                            <template x-for="sup in supplierQuickPayees" :key="sup.id">
                                <option :value="sup.id" x-text="sup.name"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="drawerTab === 'customer_settlement'" class="space-y-3">
                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">اختر العميل</label>
                            <select x-model="drawerForm.customer_id" class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                <option value="">— اختر العميل —</option>
                                <template x-for="c in customers" :key="`${c.id}-${c.client_uuid}`">
                                    <option :value="c.id" x-text="`${c.name} (${fmtMoney(c.current_balance)})`"></option>
                                </template>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block font-semibold text-slate-700">وسيلة القبض</label>
                            <select x-model="drawerForm.method" class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                                <option value="cash">نقدي</option>
                                <option value="kuraimi">الكريمي</option>
                                <option value="jawali">جوالي</option>
                                <option value="floosak">فلوسك</option>
                                <option value="onecash">ون كاش</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">
                            المبلغ (<span x-text="currencySymbol()"></span>) <span class="text-rose-600">*</span>
                        </label>
                        <input type="number" step="any" min="0.01" x-model="drawerForm.amount" @change="if (drawerForm.amount !== '') drawerForm.amount = formatInputMoney(drawerForm.amount)" required
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 font-bold text-sm text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:outline-none">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">ملاحظات</label>
                        <input type="text" x-model="drawerForm.notes"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        <span>حفظ وإصدار سند الدرج</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Manager Override PIN Modal (for F2 quick checkout below cost) -->
        <div x-show="modal === 'override_pin'" class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 space-y-4 shadow-xl text-center">
            <div class="w-11 h-11 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-600 mx-auto flex items-center justify-center">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                </svg>
            </div>
            <div class="space-y-1">
                <h3 class="font-bold text-base text-slate-900">موافقة المدير (بيع أقل من التكلفة)</h3>
                <p class="text-xs text-gray-500">أدخل رمز PIN الخاص بالمدير أو المالك لإتمام الفاتورة:</p>
            </div>
            <div x-show="overridePinError" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-bold" x-text="overridePinError"></div>
            <form @submit.prevent="confirmManagerOverrideModal()" class="space-y-3">
                <input type="password" inputmode="numeric" maxlength="4" x-model="overridePinInput" autofocus
                       class="w-full text-center text-2xl tracking-[0.5em] font-mono font-bold tabular-nums rounded-xl border border-gray-200 bg-gray-50/70 py-2.5 focus:bg-white focus:border-amber-600 focus:outline-none">
                <div class="flex gap-2">
                    <button type="button" @click="closeModal()" class="flex-1 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-xs font-bold transition-colors cursor-pointer">إلغاء</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">موافقة</button>
                </div>
            </form>
        </div>

        <!-- Setup PIN Modal -->
        <div x-show="modal === 'setup_pin'" class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-6 space-y-4 shadow-xl text-center">
            <div class="w-11 h-11 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-600 mx-auto flex items-center justify-center">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>
            <div class="space-y-1">
                <h3 class="font-bold text-base text-slate-900">تعيين رمز القفل السريع PIN</h3>
                <p class="text-xs text-gray-500">أدخل 4 أرقام لقفل شاشة الكاشير مؤقتاً دون مسح السلة.</p>
            </div>
            <div x-show="lockError" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-bold" x-text="lockError"></div>
            <form @submit.prevent="saveQuickPinAndLock()" class="space-y-3">
                <input type="password" inputmode="numeric" maxlength="4" x-model="lockSetupPinInput" autofocus placeholder="4 أرقام"
                       class="w-full text-center text-2xl tracking-[0.5em] font-mono font-bold tabular-nums rounded-xl border border-gray-200 bg-gray-50/70 py-2.5 placeholder:text-xs placeholder:tracking-normal placeholder:font-sans focus:bg-white focus:border-amber-600 focus:outline-none">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                    حفظ الرمز وقفل الشاشة
                </button>
            </form>
        </div>

        <!-- Encrypted Emergency Backup Modal -->
        <div x-show="modal === 'backup'" class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <h3 class="font-bold text-sm text-slate-900">تصدير نسخة طوارئ مشفرة (AES-256-GCM)</h3>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-xs text-gray-600 leading-relaxed">
                    يتم تشفير كافة العمليات المعلقة في طابور المزامنة وإعدادات الجهاز محلياً عبر Web Crypto API في ملف JSON آمن.
                </p>
                <div x-show="backupStatusMsg" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold" x-text="backupStatusMsg"></div>
                <div class="space-y-3 text-xs">
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">كلمة مرور التشفير (اختياري — الافتراضي رمز الجهاز)</label>
                        <input type="password" x-model="backupPassphrase" placeholder="أدخل كلمة مرور قوية..."
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                    </div>
                    <button type="button" @click="downloadEncryptedBackup()"
                            class="w-full inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-xs transition-colors cursor-pointer">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        <span>تنزيل النسخة المشفرة JSON</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Outbox Inspector Modal -->
        <div x-show="modal === 'outbox'" class="w-full max-w-xl rounded-2xl border border-gray-200 bg-white shadow-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-sm text-slate-900">طابور المزامنة المحلي</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-slate-700 tabular-nums" x-text="pendingOutboxItems.length"></span>
                </div>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div class="text-xs text-gray-500">
                    آخر مزامنة ناجحة: <strong class="text-slate-800 font-mono tabular-nums" x-text="lastSyncAt || 'لم تتم بعد'"></strong>
                </div>
                <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto text-xs border-y border-gray-100">
                    <template x-for="item in pendingOutboxItems" :key="item.client_uuid">
                        <div class="py-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 font-mono truncate" x-text="`${item.entity_type} — ${item.client_uuid.slice(0, 8)}...`"></div>
                                <div class="text-gray-500 font-mono tabular-nums mt-0.5" x-text="item.created_at"></div>
                                <div x-show="item.last_error" class="text-rose-600 font-semibold mt-1" x-text="item.last_error"></div>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold shrink-0 border"
                                  :class="item.status === 'error' ? 'bg-rose-50 text-rose-700 border-rose-200/70' : 'bg-amber-50 text-amber-800 border-amber-200/70'"
                                  x-text="item.status"></span>
                        </div>
                    </template>
                    <div x-show="pendingOutboxItems.length === 0" class="py-10 text-center space-y-2">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        </div>
                        <p class="text-emerald-700 font-bold">كافة العمليات متزامنة مع السيرفر ولا توجد سجلات معلقة.</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="button" @click="syncNow(true)" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                            <path d="M3 3v5h5" />
                            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
                            <path d="M16 16h5v5" />
                        </svg>
                        <span>إعادة المحاولة الآن</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>