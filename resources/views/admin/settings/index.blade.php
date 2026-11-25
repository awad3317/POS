@extends('layouts.app')
@section('title', 'الإعدادات والموظفين والأجهزة')
@section('header', 'إعدادات المتجر وسعر الصرف والأجهزة المعتمدة')

@section('content')
@php
    $flags = $store->feature_flags ?? [];
    $receipt = $store->receipt_settings ?? [];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start text-xs">

    <!-- Right Column: Store Settings, Exchange Rate & Printing Guide -->
    <div class="lg:col-span-6 space-y-6">

        <!-- Store General Settings Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900">الإعدادات العامة وميزات النشاط</h2>
                    <p class="text-[11px] text-gray-500 mt-0.5">هوية المتجر ونوع النشاط وخيارات الطباعة والميزات المفعّلة</p>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.update') }}" class="p-5 space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">
                            اسم المحل <span class="text-rose-600">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               value="{{ $store->name }}"
                               required
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">نوع النشاط</label>
                        <select name="business_type"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            <option value="grocery" @selected($store->business_type === 'grocery')>بقالة / سوبرماركت</option>
                            <option value="clothing" @selected($store->business_type === 'clothing')>ملابس وأحذية</option>
                            <option value="perfumes" @selected($store->business_type === 'perfumes')>عطور</option>
                            <option value="general" @selected($store->business_type === 'general')>تجزئة عامة</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">العملة الثانوية للصرف</label>
                        <input type="text"
                               name="secondary_currency_code"
                               value="{{ $store->secondary_currency_code }}"
                               placeholder="SAR أو USD"
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-mono text-slate-900 placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-semibold text-slate-700">مقاس الطابعة</label>
                        <select name="paper_width"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                            <option value="80mm" @selected(($receipt['paper_width'] ?? '80mm') === '80mm')>80mm</option>
                            <option value="58mm" @selected(($receipt['paper_width'] ?? '80mm') === '58mm')>58mm</option>
                        </select>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 space-y-2.5">
                    <div class="text-[11px] font-bold text-gray-500">ميزات النشاط المفعّلة</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <label class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-gray-50/60 hover:bg-gray-50 font-semibold text-slate-700 cursor-pointer transition-colors">
                            <input type="checkbox"
                                   name="feature_flags[variants]"
                                   value="1"
                                   @checked(!empty($flags['variants']))
                                   class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            <span>المقاسات والألوان</span>
                        </label>

                        <label class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-gray-50/60 hover:bg-gray-50 font-semibold text-slate-700 cursor-pointer transition-colors">
                            <input type="checkbox"
                                   name="feature_flags[multi_units]"
                                   value="1"
                                   @checked(!empty($flags['multi_units']))
                                   class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            <span>تعدد الوحدات</span>
                        </label>

                        <label class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-gray-50/60 hover:bg-gray-50 font-semibold text-slate-700 cursor-pointer transition-colors">
                            <input type="checkbox"
                                   name="feature_flags[scale_plu]"
                                   value="1"
                                   @checked(!empty($flags['scale_plu']))
                                   class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            <span>باركود الميزان</span>
                        </label>

                        <label class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl border border-gray-200 bg-gray-50/60 hover:bg-gray-50 font-semibold text-slate-700 cursor-pointer transition-colors">
                            <input type="checkbox"
                                   name="feature_flags[exchange_rate]"
                                   value="1"
                                   @checked(!empty($flags['exchange_rate']))
                                   class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                            <span>تحديث سعر الصرف</span>
                        </label>
                    </div>
                </div>

                <div class="pt-1 flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        <span>حفظ الإعدادات</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Exchange Rate Tool Card -->
        <div class="rounded-2xl border border-amber-200/80 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-amber-100 bg-amber-50/40 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                        <path d="M21 3v5h-5" />
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                        <path d="M8 16H3v5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-bold text-slate-900">أداة تحديث سعر الصرف الفوري</h3>
                    <p class="text-[11px] text-amber-800/80 mt-0.5">إعادة تسعير الأصناف المرتبطة بالعملة الثانوية تلقائياً</p>
                </div>
            </div>

            <div class="p-5">
                <form method="POST" action="{{ route('settings.exchange-rate') }}" class="flex flex-col sm:flex-row gap-2.5">
                    @csrf
                    <input type="number"
                           step="any"
                           name="exchange_rate"
                           value="{{ format_input($store->exchange_rate, true) }}"
                           required
                           placeholder="سعر الصرف الجديد"
                           class="flex-1 rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-xs font-bold text-slate-900 tabular-nums placeholder:font-normal placeholder:text-gray-400 focus:bg-white focus:border-amber-600 focus:ring-2 focus:ring-amber-600/15 focus:outline-none transition-colors">
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold shadow-xs transition-colors shrink-0 cursor-pointer">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                            <path d="M3 3v5h5" />
                            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
                            <path d="M16 16h5v5" />
                        </svg>
                        <span>تحديث وإعادة التسعير</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Silent Thermal Printing Guide Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-5 space-y-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9V2h12v7" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                </div>
                <h3 class="text-xs font-bold text-slate-900">دليل الطباعة الحرارية الصامتة (--kiosk-printing)</h3>
            </div>
            <p class="text-[11px] leading-relaxed text-gray-600">
                لتخطي نافذة معاينة الطباعة في Chrome أو Edge وإخراج الفاتورة تلقائياً على الطابعة الافتراضية، أضف العلم التالي لاختصار المتصفح:
            </p>
            <code class="block rounded-xl bg-slate-900 text-emerald-300 px-3.5 py-2.5 font-mono text-[11px] text-left select-all" dir="ltr">chrome.exe --kiosk-printing</code>
        </div>

    </div>

    <!-- Left Column: Terminals & Users -->
    <div class="lg:col-span-6 space-y-6">

        <!-- Terminals Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2" />
                            <line x1="8" y1="21" x2="16" y2="21" />
                            <line x1="12" y1="17" x2="12" y2="21" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-slate-900 truncate">أجهزة الكاشير المعتمدة</h2>
                        <p class="text-[11px] text-gray-500 mt-0.5">إضافة نقاط البيع أو الإيقاف الفوري عن بُعد</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                    {{ $terminals->count() }} جهاز
                </span>
            </div>

            <div class="p-5 space-y-4">
                <form method="POST" action="{{ route('settings.terminals.store') }}" class="flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text"
                           name="code"
                           required
                           placeholder="الرمز (T02)"
                           class="sm:w-32 rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs font-mono font-semibold text-slate-900 placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <input type="text"
                           name="name"
                           required
                           placeholder="اسم الجهاز"
                           class="flex-1 rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold shrink-0 transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        <span>إضافة</span>
                    </button>
                </form>

                <div class="divide-y divide-gray-100 border-t border-gray-100">
                    @forelse ($terminals as $term)
                        <div class="py-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-100 text-slate-800 font-mono font-bold text-[11px] tabular-nums">
                                    {{ $term->code }}
                                </span>
                                <span class="font-semibold text-slate-800 truncate">{{ $term->name }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $term->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/70' : 'bg-rose-50 text-rose-700 border border-rose-200/70' }}">
                                    {{ $term->is_active ? 'نشط' : 'موقوف' }}
                                </span>
                            </div>

                            @if ($term->is_active)
                                <form method="POST" action="{{ route('settings.terminals.revoke', $term->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-600 text-rose-700 hover:text-white border border-rose-200/80 hover:border-rose-600 text-[11px] font-semibold transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
                                        </svg>
                                        <span>إيقاف وإبطال التوكن</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">لا توجد أجهزة كاشير مضافة بعد.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Users & Permissions Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-slate-900 truncate">حسابات الدخول والصلاحيات ورمز PIN</h2>
                        <p class="text-[11px] text-gray-500 mt-0.5">إنشاء حسابات المستخدمين وتعيين الأدوار ورموز الدخول السريع</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 shrink-0 tabular-nums">
                    {{ $users->count() }} حساب
                </span>
            </div>

            <div class="p-5 space-y-4">
                <form method="POST" action="{{ route('settings.users.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @csrf
                    <input type="text"
                           name="name"
                           required
                           placeholder="الاسم"
                           class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">

                    <input type="email"
                           name="email"
                           required
                           placeholder="البريد"
                           class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">

                    <input type="password"
                           name="password"
                           required
                           placeholder="كلمة المرور (8+)"
                           class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">

                    <input type="text"
                           name="pin"
                           maxlength="4"
                           placeholder="PIN (4 أرقام)"
                           class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs font-mono text-slate-900 tabular-nums placeholder:font-sans placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">

                    <select name="role"
                            class="rounded-xl border border-gray-200 bg-gray-50/70 px-3 py-2 text-xs text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        <option value="cashier">كاشير (cashier)</option>
                        <option value="storekeeper">أمين مخزن (storekeeper)</option>
                        <option value="manager">مدير فرع (manager)</option>
                        <option value="owner">مالك (owner)</option>
                    </select>

                    <button type="submit"
                            class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2 px-4 shadow-xs transition-colors cursor-pointer">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        <span>إنشاء حساب</span>
                    </button>
                </form>

                <div class="divide-y divide-gray-100 border-t border-gray-100">
                    @forelse ($users as $u)
                        <div class="py-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate">{{ $u->name }}</div>
                                <div class="text-[11px] text-gray-500 font-mono truncate">{{ $u->email }}</div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-100 text-slate-700 border border-gray-200/70 shrink-0">
                                {{ $u->getRoleNames()->implode(', ') }}
                            </span>
                        </div>
                    @empty
                        <div class="py-8 text-center text-gray-400">لا توجد حسابات مستخدمين مسجلة.</div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

</div>
@endsection