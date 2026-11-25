@extends('layouts.super-admin')
@section('title', 'إدارة المتاجر والاشتراكات')

@section('content')
@php
    $creds = session('created_credentials');
@endphp

<div x-data="{
        createModal: {{ $errors->hasAny(['store_name', 'owner_name', 'owner_email', 'owner_password', 'owner_pin']) ? 'true' : 'false' }},
        extendTarget: null,
        resetTarget: null,
        copiedToast: false,
        copyCredentials(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copiedToast = true;
                    setTimeout(() => { this.copiedToast = false; }, 2500);
                });
            }
        }
    }" class="space-y-6">

    <!-- Flashed Arabic Credentials Card -->
    @if ($creds)
    <div class="rounded-2xl border border-emerald-700 bg-slate-900 text-white shadow-sm overflow-hidden">
        <div class="p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-500/35 text-emerald-300 text-[11px] font-bold">
                            بطاقة بيانات الدخول الجاهزة للإرسال
                        </span>
                        <h2 class="text-sm sm:text-base font-bold text-white mt-1 truncate">
                            بيانات متجر: {{ $creds['store_name'] }} — المالك: {{ $creds['owner_name'] }}
                        </h2>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs shrink-0">
                    <button type="button"
                            @click="copyCredentials(@js($creds['copy_text']))"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-gray-100 text-slate-900 font-bold transition-colors cursor-pointer">
                        <svg x-show="!copiedToast" class="w-3.5 h-3.5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                        </svg>
                        <svg x-show="copiedToast" style="display: none;" class="w-3.5 h-3.5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                        <span x-show="!copiedToast">نسخ البطاقة</span>
                        <span x-show="copiedToast" style="display: none;" class="text-emerald-700">تم النسخ</span>
                    </button>

                    <a href="{{ $creds['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition-colors">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                        </svg>
                        <span>إرسال عبر واتساب</span>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs bg-slate-800/80 rounded-xl p-4 border border-slate-700/80">
                <div class="min-w-0">
                    <div class="text-[11px] text-emerald-300 font-medium">رابط الدخول</div>
                    <div class="font-mono font-bold text-white mt-1 truncate select-all" dir="ltr">{{ $creds['login_url'] }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] text-emerald-300 font-medium">البريد الإلكتروني</div>
                    <div class="font-mono font-bold text-white mt-1 truncate select-all" dir="ltr">{{ $creds['email'] }}</div>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] text-emerald-300 font-medium">كلمة المرور</div>
                    <div class="font-mono font-bold text-white mt-1 select-all" dir="ltr">{{ $creds['password'] }}</div>
                </div>
                @if (!empty($creds['pin']))
                <div class="min-w-0">
                    <div class="text-[11px] text-emerald-300 font-medium">رمز PIN للمالك</div>
                    <div class="font-mono font-bold text-white tabular-nums mt-1 select-all" dir="ltr">{{ $creds['pin'] }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- 1. Platform-Wide KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 flex flex-col justify-between gap-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-gray-500">إجمالي المتاجر</span>
                <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-slate-900 tabular-nums">{{ format_qty($kpis['total_stores']) }}</div>
        </div>

        <div class="rounded-2xl border border-emerald-200/80 bg-white shadow-xs p-5 flex flex-col justify-between gap-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-emerald-700">المتاجر النشطة</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-emerald-700 tabular-nums">{{ format_qty($kpis['active_stores']) }}</div>
        </div>

        <div class="rounded-2xl border border-amber-200/80 bg-white shadow-xs p-5 flex flex-col justify-between gap-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-amber-700">الفترة التجريبية</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200/70 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-amber-700 tabular-nums">{{ format_qty($kpis['trial_stores']) }}</div>
        </div>

        <div class="rounded-2xl border border-rose-200/80 bg-white shadow-xs p-5 flex flex-col justify-between gap-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-rose-700">موقوفة / منتهية</span>
                <div class="w-9 h-9 rounded-xl bg-rose-50 border border-rose-200/70 text-rose-600 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-rose-700 tabular-nums">{{ format_qty($kpis['suspended_stores']) }}</div>
        </div>

        <div class="rounded-2xl border border-slate-900 bg-slate-900 text-white shadow-xs p-5 col-span-2 sm:col-span-1 flex flex-col justify-between gap-3">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-slate-300">فواتير اليوم عبر المنصة</span>
                <div class="w-9 h-9 rounded-xl bg-white/10 text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" />
                        <path d="M8 7h8M8 11h8M8 15h5" />
                    </svg>
                </div>
            </div>
            <div class="text-2xl font-extrabold text-emerald-400 tabular-nums">{{ format_qty($kpis['today_orders']) }}</div>
        </div>
    </div>

    <!-- 2. Search, Filter & Create Store Bar -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
        <form method="GET" action="{{ route('super-admin.index') }}" class="flex flex-wrap items-center gap-2.5 flex-1">
            <div class="relative w-full sm:w-64">
                <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                </span>
                <input type="text"
                       name="q"
                       value="{{ $search }}"
                       placeholder="ابحث باسم المتجر، المالك، البريد، أو الجوال..."
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 pr-9 pl-3 py-2 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
            </div>

            <select name="business_type"
                    class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                <option value="">كل الأنشطة التجارية</option>
                <option value="grocery" @selected($businessType === 'grocery')>بقالة / سوبرماركت</option>
                <option value="clothing" @selected($businessType === 'clothing')>ملابس وأحذية</option>
                <option value="perfumes" @selected($businessType === 'perfumes')>عطور وتجميل</option>
                <option value="general" @selected($businessType === 'general')>تجزئة عامة</option>
            </select>

            <select name="subscription_status"
                    class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                <option value="">كل حالات الاشتراك</option>
                <option value="active" @selected($subscriptionStatus === 'active')>نشط (Active)</option>
                <option value="trial" @selected($subscriptionStatus === 'trial')>تجريبي (Trial)</option>
                <option value="suspended" @selected($subscriptionStatus === 'suspended')>موقوف (Suspended)</option>
                <option value="expired" @selected($subscriptionStatus === 'expired')>منتهي الصلاحية</option>
            </select>

            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold transition-colors cursor-pointer">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                </svg>
                <span>تصفية</span>
            </button>

            @if ($search !== '' || $businessType !== '' || $subscriptionStatus !== '')
                <a href="{{ route('super-admin.index') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 font-semibold transition-colors">
                    إعادة ضبط
                </a>
            @endif
        </form>

        <button type="button"
                @click="createModal = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors shrink-0 cursor-pointer">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 5v14M5 12h14" />
            </svg>
            <span>إنشاء متجر ومالك جديد</span>
        </button>
    </div>

    <!-- 3. Stores Table -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18" />
                        <path d="M5 21V7l8-4v18" />
                        <path d="M19 21V11l-6-4" />
                        <path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">سجل المتاجر والاشتراكات</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">التحكم بحالة الاشتراك وتمديد الصلاحية وبيانات الملاك</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                {{ $stores->count() }} متجر في الصفحة
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-[#F9FAFB] text-gray-500 border-b border-gray-200 text-[11px] font-semibold">
                    <tr>
                        <th class="px-4 py-3.5">المتجر</th>
                        <th class="px-4 py-3.5">نوع النشاط</th>
                        <th class="px-4 py-3.5">بيانات المالك</th>
                        <th class="px-4 py-3.5">الأجهزة / الفواتير</th>
                        <th class="px-4 py-3.5">حالة الاشتراك</th>
                        <th class="px-4 py-3.5">ينتهي في</th>
                        <th class="px-4 py-3.5">إجراءات التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($stores as $st)
                    @php
                        $owner = $st->users->first(fn ($u) => $u->hasRole('owner')) ?? $st->users->first();
                        $isExpired = $st->isSubscriptionExpired();
                        $typeBadgeColors = [
                            'grocery' => 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
                            'clothing' => 'bg-indigo-50 text-indigo-800 border-indigo-200/80',
                            'perfumes' => 'bg-amber-50 text-amber-800 border-amber-200/80',
                            'general' => 'bg-gray-100 text-slate-700 border-gray-200',
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50/80 transition-colors">
                        <td class="px-4 py-3.5">
                            <div class="font-bold text-slate-900 text-sm">{{ $st->name }}</div>
                            <div class="text-[11px] text-gray-500 flex flex-wrap items-center gap-1.5 mt-1">
                                <span class="font-mono text-gray-600">{{ $st->slug }}</span>
                                <span class="text-gray-300">•</span>
                                <span>{{ $st->currency_code }} ({{ $st->currency_symbol }})</span>
                                @if (!$st->onboarding_completed_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-sky-50 border border-sky-200/70 text-sky-700 text-[10px] font-semibold">
                                        بانتظار التهيئة
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td class="px-4 py-3.5 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg border text-[11px] font-bold {{ $typeBadgeColors[$st->business_type] ?? $typeBadgeColors['general'] }}">
                                {{ $st->businessTypeLabel() }}
                            </span>
                        </td>

                        <td class="px-4 py-3.5">
                            @if ($owner)
                                <div class="font-semibold text-slate-900">{{ $owner->name }}</div>
                                <div class="text-[11px] text-gray-500 font-mono mt-0.5" dir="ltr">{{ $owner->email }}</div>
                                @if ($owner->phone)
                                    <div class="text-[11px] text-gray-500 font-mono tabular-nums" dir="ltr">{{ $owner->phone }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">بدون مالك</span>
                            @endif
                        </td>

                        <td class="px-4 py-3.5 whitespace-nowrap tabular-nums">
                            <div class="text-slate-700">الأجهزة: <strong class="text-slate-900">{{ format_qty($st->terminals_count) }}</strong></div>
                            <div class="text-gray-500 mt-0.5">الفواتير: <strong class="text-slate-800">{{ format_qty($st->orders_count) }}</strong></div>
                        </td>

                        <td class="px-4 py-3.5 whitespace-nowrap">
                            @if ($st->subscription_status === 'suspended')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200/70 text-[11px] font-bold">موقوف</span>
                            @elseif ($isExpired)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200/70 text-[11px] font-bold">منتهي الصلاحية</span>
                            @elseif ($st->subscription_status === 'trial')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200/70 text-[11px] font-bold">تجريبي</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/70 text-[11px] font-bold">نشط</span>
                            @endif
                        </td>

                        <td class="px-4 py-3.5 font-mono font-semibold tabular-nums whitespace-nowrap {{ $isExpired ? 'text-rose-600' : 'text-slate-700' }}">
                            {{ $st->subscription_ends_at ? $st->subscription_ends_at->format('Y-m-d') : 'مفتوح' }}
                        </td>

                        <td class="px-4 py-3.5 whitespace-nowrap">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <form method="POST" action="{{ route('super-admin.stores.toggle-status', $st->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition-colors cursor-pointer {{ $st->subscription_status === 'suspended' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200/80 hover:border-rose-600' }}">
                                        {{ $st->subscription_status === 'suspended' ? 'إعادة تفعيل' : 'إيقاف مؤقت' }}
                                    </button>
                                </form>

                                <button type="button"
                                        @click="extendTarget = { id: {{ $st->id }}, name: @js($st->name), ends_at: @js($st->subscription_ends_at?->format('Y-m-d') ?? '') }"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-[11px] font-bold transition-colors cursor-pointer">
                                    تمديد الاشتراك
                                </button>

                                @if ($owner)
                                <button type="button"
                                        @click="resetTarget = { id: {{ $st->id }}, name: @js($st->name), owner_email: @js($owner->email) }"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-[11px] font-semibold transition-colors cursor-pointer">
                                    كلمة المرور
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-14 text-center">
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                                <svg class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                    <polyline points="9 22 9 12 15 12 15 22" />
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-700">لا توجد متاجر مطابقة لمعايير البحث الحالية.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($stores->hasPages())
            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB]">
                {{ $stores->links() }}
            </div>
        @endif
    </div>

    <!-- Modal 1: Create Store + Owner Account -->
    <div x-show="createModal" style="display: none;"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4 overflow-y-auto">
        <form method="POST" action="{{ route('super-admin.stores.store') }}"
              @click.outside="createModal = false"
              class="rounded-2xl border border-gray-200 bg-white max-w-2xl w-full overflow-hidden text-xs shadow-2xl my-8">
            @csrf

            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-sm sm:text-base text-slate-900 truncate">إنشاء متجر جديد وحساب المالك</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">سيتم توجيه التاجر تلقائياً لمعالج التهيئة السريعة (60 ثانية) عند أول دخول</p>
                    </div>
                </div>
                <button type="button" @click="createModal = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2 flex items-center gap-2 border-b border-gray-100 pb-2">
                    <span class="w-6 h-6 rounded-lg bg-slate-900 text-white text-[11px] font-bold flex items-center justify-center tabular-nums">1</span>
                    <span class="font-bold text-slate-900">بيانات المتجر والاشتراك</span>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        اسم المتجر / المحل <span class="text-rose-600">*</span>
                    </label>
                    <input type="text" name="store_name" value="{{ old('store_name') }}" required placeholder="مثال: سوبرماركت النجم"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        نوع النشاط <span class="text-rose-600">*</span>
                    </label>
                    <select name="business_type"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="grocery" @selected(old('business_type') === 'grocery')>بقالة / سوبرماركت</option>
                        <option value="clothing" @selected(old('business_type') === 'clothing')>ملابس وأحذية</option>
                        <option value="perfumes" @selected(old('business_type') === 'perfumes')>عطور وتجميل</option>
                        <option value="general" @selected(old('business_type') === 'general')>تجزئة عامة</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">العملة الأساسية</label>
                    <select name="currency_code"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="YER" @selected(old('currency_code', 'YER') === 'YER')>ريال يمني (YER)</option>
                        <option value="SAR" @selected(old('currency_code') === 'SAR')>ريال سعودي (SAR)</option>
                        <option value="USD" @selected(old('currency_code') === 'USD')>دولار أمريكي (USD)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">رمز العملة</label>
                        <input type="text" name="currency_symbol" value="{{ old('currency_symbol', 'ر.ي') }}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">الكسور العشرية</label>
                        <select name="decimal_places"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            <option value="0" @selected((int) old('decimal_places', 0) === 0)>0 (بدون كسور)</option>
                            <option value="2" @selected((int) old('decimal_places') === 2)>2 (0.00)</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        حالة الاشتراك الابتدائية <span class="text-rose-600">*</span>
                    </label>
                    <select name="subscription_status"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="active" @selected(old('subscription_status', 'active') === 'active')>نشط مدفوع (Active)</option>
                        <option value="trial" @selected(old('subscription_status') === 'trial')>فترة تجريبية (Trial)</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">تاريخ انتهاء الاشتراك (اختياري)</label>
                    <input type="date" name="subscription_ends_at" value="{{ old('subscription_ends_at', now()->addYear()->format('Y-m-d')) }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 font-mono tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="sm:col-span-2 flex items-center gap-2 border-b border-gray-100 pb-2 pt-2">
                    <span class="w-6 h-6 rounded-lg bg-slate-900 text-white text-[11px] font-bold flex items-center justify-center tabular-nums">2</span>
                    <span class="font-bold text-slate-900">بيانات مالك المتجر (حساب الإدارة)</span>
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        اسم المالك الكامل <span class="text-rose-600">*</span>
                    </label>
                    <input type="text" name="owner_name" value="{{ old('owner_name') }}" required placeholder="الاسم الثلاثي"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">رقم الجوال / واتساب</label>
                    <input type="text" name="owner_phone" value="{{ old('owner_phone') }}" placeholder="77XXXXXXX"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 font-mono tabular-nums placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors" dir="ltr">
                </div>

                <div class="space-y-1.5">
                    <label class="block font-semibold text-slate-700">
                        البريد الإلكتروني للدخول <span class="text-rose-600">*</span>
                    </label>
                    <input type="email" name="owner_email" value="{{ old('owner_email') }}" required placeholder="owner@store.ye"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 font-mono placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors" dir="ltr">
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    <div class="col-span-2 space-y-1.5">
                        <label class="block font-semibold text-slate-700">
                            كلمة المرور (8+ أحرف) <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" name="owner_password" value="{{ old('owner_password', 'TayarPlus@' . rand(1000, 9999)) }}" required minlength="8"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-slate-900 font-mono focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors" dir="ltr">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">رمز PIN</label>
                        <input type="text" name="owner_pin" value="{{ old('owner_pin', '1234') }}" maxlength="4" pattern="\d{4}"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2.5 text-center text-slate-900 font-mono tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors" dir="ltr">
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2.5">
                <button type="button" @click="createModal = false"
                        class="px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                    <span>إنشاء المتجر وتوليد بطاقة الدخول</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal 2: Extend Subscription -->
    <div x-show="extendTarget !== null" style="display: none;"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4">
        <div @click.outside="extendTarget = null"
             class="rounded-2xl border border-gray-200 bg-white max-w-md w-full overflow-hidden text-xs shadow-2xl">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900 truncate">
                        تمديد اشتراك: <span class="text-emerald-700" x-text="extendTarget?.name"></span>
                    </h3>
                </div>
                <button type="button" @click="extendTarget = null"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div class="space-y-2.5">
                    <div class="font-semibold text-gray-600">تمديد فوري بضغطة واحدة (مع تفعيل المتجر تلقائياً):</div>
                    <div class="grid grid-cols-3 gap-2">
                        <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`">
                            @csrf
                            <input type="hidden" name="preset" value="+1 month">
                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-bold transition-colors cursor-pointer">
                                + شهر واحد
                            </button>
                        </form>
                        <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`">
                            @csrf
                            <input type="hidden" name="preset" value="+3 months">
                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-bold transition-colors cursor-pointer">
                                + 3 أشهر
                            </button>
                        </form>
                        <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`">
                            @csrf
                            <input type="hidden" name="preset" value="+1 year">
                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                                + سنة كاملة
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`" class="space-y-3.5 pt-4 border-t border-gray-100">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">أو تحديد تاريخ انتهاء مخصص:</label>
                        <input type="date" name="subscription_ends_at" :value="extendTarget?.ends_at" required
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 font-mono tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="extendTarget = null"
                                class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                            إلغاء
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold shadow-xs transition-colors cursor-pointer">
                            حفظ التاريخ المخصص
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 3: Reset Store Owner Password -->
    <div x-show="resetTarget !== null" style="display: none;"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST" :action="`/super-admin/stores/${resetTarget?.id}/reset-owner-password`"
              @click.outside="resetTarget = null"
              class="rounded-2xl border border-gray-200 bg-white max-w-md w-full overflow-hidden text-xs shadow-2xl">
            @csrf

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-rose-50 border border-rose-200/70 text-rose-600 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-sm text-slate-900">إعادة تعيين كلمة مرور المالك</h3>
                        <div class="text-[11px] text-gray-500 mt-0.5 truncate">
                            المتجر: <span class="font-semibold text-slate-700" x-text="resetTarget?.name"></span>
                            (<span class="font-mono" dir="ltr" x-text="resetTarget?.owner_email"></span>)
                        </div>
                    </div>
                </div>
                <button type="button" @click="resetTarget = null"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-slate-700 hover:bg-gray-100 transition-colors cursor-pointer shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-1.5">
                <label class="block font-semibold text-slate-700">
                    كلمة المرور الجديدة (8 أحرف على الأقل) <span class="text-rose-600">*</span>
                </label>
                <input type="text" name="password" required minlength="8" placeholder="أدخل كلمة المرور الجديدة"
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-slate-900 font-mono placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors" dir="ltr">
            </div>

            <div class="px-5 py-3.5 border-t border-gray-100 bg-[#F9FAFB] flex justify-end gap-2">
                <button type="button" @click="resetTarget = null"
                        class="px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 font-semibold transition-colors cursor-pointer">
                    إلغاء
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold shadow-xs transition-colors cursor-pointer">
                    تحديث كلمة المرور
                </button>
            </div>
        </form>
    </div>

</div>

@endsection