<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#059669">
    <link rel="icon" type="image/png" href="{{ asset('icons/icon-192.png') }}?v=2">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}?v=2">
    <title>تهيئة المتجر السريعة — تيار بلس</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F9FAFB] text-slate-900 py-8 sm:py-12 px-4 font-sans antialiased">
<div class="max-w-2xl mx-auto space-y-6"
     x-data="{ step: 1, businessType: '{{ old('business_type', $store->business_type ?? 'grocery') }}', paperWidth: '{{ old('paper_width', '80mm') }}' }">

    <!-- Top Brand Header -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <x-brand-logo size="md" />
            <div>
                <div class="text-base font-extrabold text-slate-900 leading-tight">تيار بلس</div>
                <div class="text-[11px] font-medium text-gray-500">نظام نقاط البيع والمحاسبة السحابي</div>
            </div>
        </div>

        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-[11px] font-bold tabular-nums">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
            <span>تهيئة في 60 ثانية</span>
        </span>
    </div>

    <!-- Main Wizard Card -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">

        <!-- Wizard Header & Stepper -->
        <div class="px-6 sm:px-8 pt-6 sm:pt-8 pb-5 border-b border-gray-100 space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <span class="text-xs font-bold text-emerald-600">معالج التهيئة السريعة</span>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 mt-1">لنجهّز متجرك للعمل فوراً</h1>
                </div>
                <div class="text-xs font-semibold text-gray-500 tabular-nums">
                    الخطوة <span class="text-slate-900 font-bold" x-text="step"></span> من 3
                </div>
            </div>

            <!-- 3-Step Indicator & Progress Bar -->
            <div class="grid grid-cols-3 gap-3">
                <div class="space-y-2">
                    <div :class="step >= 1 ? 'bg-emerald-600' : 'bg-gray-200'" class="h-1.5 rounded-full transition-colors"></div>
                    <div class="flex items-center gap-1.5 text-[11px] font-bold" :class="step >= 1 ? 'text-slate-900' : 'text-gray-400'">
                        <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] tabular-nums"
                              :class="step >= 1 ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-400'">1</span>
                        <span class="truncate">المحل والعملة</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div :class="step >= 2 ? 'bg-emerald-600' : 'bg-gray-200'" class="h-1.5 rounded-full transition-colors"></div>
                    <div class="flex items-center gap-1.5 text-[11px] font-bold" :class="step >= 2 ? 'text-slate-900' : 'text-gray-400'">
                        <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] tabular-nums"
                              :class="step >= 2 ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-400'">2</span>
                        <span class="truncate">نوع النشاط</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div :class="step >= 3 ? 'bg-emerald-600' : 'bg-gray-200'" class="h-1.5 rounded-full transition-colors"></div>
                    <div class="flex items-center gap-1.5 text-[11px] font-bold" :class="step >= 3 ? 'text-slate-900' : 'text-gray-400'">
                        <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] tabular-nums"
                              :class="step >= 3 ? 'bg-slate-900 text-white' : 'bg-gray-100 text-gray-400'">3</span>
                        <span class="truncate">الفاتورة الحرارية</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-8">
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                    <div class="font-bold flex items-center gap-1.5 mb-1">
                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        <span>يرجى مراجعة الحقول التالية:</span>
                    </div>
                    @foreach ($errors->all() as $e)
                        <div class="pr-5">• {{ $e }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('onboarding.store') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- Step 1: Store Identity & Currency -->
                <div x-show="step === 1" class="space-y-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                <polyline points="9 22 9 12 15 12 15 22" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">1. بيانات المحل والعملة</h2>
                            <p class="text-[11px] text-gray-500">ستظهر هذه البيانات في الفواتير والتقارير الخاصة بمتجرك</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">
                                اسم المحل / المتجر <span class="text-rose-600">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   value="{{ old('name', $store->name) }}"
                                   required
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">رقم الهاتف / واتساب</label>
                            <input type="text"
                                   name="phone"
                                   value="{{ old('phone', $store->phone) }}"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm font-mono tabular-nums text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">العملة الأساسية</label>
                            <select name="currency_code"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                                <option value="YER" @selected(old('currency_code', $store->currency_code) === 'YER')>ريال يمني (ر.ي)</option>
                                <option value="SAR" @selected(old('currency_code', $store->currency_code) === 'SAR')>ريال سعودي (ر.س)</option>
                                <option value="USD" @selected(old('currency_code', $store->currency_code) === 'USD')>دولار أمريكي ($)</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">الخانات العشرية للعرض</label>
                            <select name="decimal_places"
                                    class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                                <option value="0" @selected((int) old('decimal_places', $store->decimal_places) === 0)>بدون كسور (0 — مناسب للريال اليمني)</option>
                                <option value="2" @selected((int) old('decimal_places', $store->decimal_places) === 2)>خانتان عشريتان (0.00)</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2 space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">العنوان</label>
                            <input type="text"
                                   name="address"
                                   value="{{ old('address', $store->address) }}"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="sm:col-span-2 space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">شعار المحل (اختياري)</label>
                            <input type="file"
                                   name="logo"
                                   accept="image/*"
                                   class="w-full rounded-xl border border-dashed border-gray-300 bg-[#F9FAFB] px-3.5 py-2.5 text-xs text-gray-600 file:ml-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3.5 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-800 file:cursor-pointer cursor-pointer">
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="button"
                                @click="step = 2"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-xs transition-colors cursor-pointer">
                            <span>التالي</span>
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 19-7-7 7-7" />
                                <path d="M19 12H5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Business Type -->
                <div x-show="step === 2" style="display:none;" class="space-y-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7" rx="1" />
                                <rect x="14" y="3" width="7" height="7" rx="1" />
                                <rect x="14" y="14" width="7" height="7" rx="1" />
                                <rect x="3" y="14" width="7" height="7" rx="1" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">2. اختر نوع نشاطك التجاري</h2>
                            <p class="text-[11px] text-gray-500">سنفعّل تلقائياً الميزات الأنسب لطبيعة مبيعاتك ويمكنك تعديلها لاحقاً</p>
                        </div>
                    </div>

                    <input type="hidden" name="business_type" :value="businessType">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <button type="button"
                                @click="businessType = 'grocery'"
                                :class="businessType === 'grocery'
                                    ? 'border-emerald-600 bg-emerald-50/60 ring-1 ring-emerald-600/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/70'"
                                class="p-4 rounded-2xl border text-right transition-all cursor-pointer flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                                 :class="businessType === 'grocery' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-slate-700'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1" />
                                    <circle cx="20" cy="21" r="1" />
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900">بقالة / سوبرماركت / عطارة</div>
                                <div class="text-xs text-gray-500 mt-1 leading-relaxed">يفعّل الوحدات المتعددة (حبة/كرتون)، باركود الميزان، والأزرار السريعة.</div>
                            </div>
                        </button>

                        <button type="button"
                                @click="businessType = 'clothing'"
                                :class="businessType === 'clothing'
                                    ? 'border-emerald-600 bg-emerald-50/60 ring-1 ring-emerald-600/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/70'"
                                class="p-4 rounded-2xl border text-right transition-all cursor-pointer flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                                 :class="businessType === 'clothing' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-slate-700'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900">ملابس / أحذية / إكسسوارات</div>
                                <div class="text-xs text-gray-500 mt-1 leading-relaxed">يفعّل المقاسات والألوان، ملصقات الباركود، وتحديث سعر الصرف.</div>
                            </div>
                        </button>

                        <button type="button"
                                @click="businessType = 'perfumes'"
                                :class="businessType === 'perfumes'
                                    ? 'border-emerald-600 bg-emerald-50/60 ring-1 ring-emerald-600/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/70'"
                                class="p-4 rounded-2xl border text-right transition-all cursor-pointer flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                                 :class="businessType === 'perfumes' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-slate-700'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 2h4" />
                                    <path d="M12 2v4" />
                                    <rect x="6" y="8" width="12" height="14" rx="2" />
                                    <path d="M9 6h6v2H9z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900">عطور ومستحضرات تجميل</div>
                                <div class="text-xs text-gray-500 mt-1 leading-relaxed">يفعّل الأحجام والتركيزات وسعر الصرف والأزرار السريعة.</div>
                            </div>
                        </button>

                        <button type="button"
                                @click="businessType = 'general'"
                                :class="businessType === 'general'
                                    ? 'border-emerald-600 bg-emerald-50/60 ring-1 ring-emerald-600/20'
                                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50/70'"
                                class="p-4 rounded-2xl border text-right transition-all cursor-pointer flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-colors"
                                 :class="businessType === 'general' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-slate-700'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                                    <path d="M3 6h18" />
                                    <path d="M16 10a4 4 0 0 1-8 0" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900">تجزئة عامة / قطع غيار / مكتبة</div>
                                <div class="text-xs text-gray-500 mt-1 leading-relaxed">واجهة مبسطة وسريعة تناسب أي محل تجاري.</div>
                            </div>
                        </button>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <button type="button"
                                @click="step = 1"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-sm font-semibold transition-colors cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 5 7 7-7 7" />
                                <path d="M5 12h14" />
                            </svg>
                            <span>السابق</span>
                        </button>

                        <button type="button"
                                @click="step = 3"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-xs transition-colors cursor-pointer">
                            <span>التالي</span>
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 19-7-7 7-7" />
                                <path d="M19 12H5" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Thermal Receipt Setup -->
                <div x-show="step === 3" style="display:none;" class="space-y-5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-200/70 text-emerald-700 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9V2h12v7" />
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                <rect x="6" y="14" width="12" height="8" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">3. إعداد الفاتورة الحرارية</h2>
                            <p class="text-[11px] text-gray-500">اختر مقاس ورق الطابعة والعبارات الترحيبية على إيصال العميل</p>
                        </div>
                    </div>

                    <input type="hidden" name="paper_width" :value="paperWidth">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <button type="button"
                                @click="paperWidth = '80mm'"
                                :class="paperWidth === '80mm'
                                    ? 'border-emerald-600 bg-emerald-50/60 text-emerald-900 ring-1 ring-emerald-600/20'
                                    : 'border-gray-200 bg-white text-slate-700 hover:border-gray-300 hover:bg-gray-50'"
                                class="p-4 rounded-2xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors"
                                 :class="paperWidth === '80mm' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-slate-600'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" />
                                    <path d="M8 7h8M8 11h8M8 15h5" />
                                </svg>
                            </div>
                            <span class="font-bold text-sm tabular-nums">طابعة حرارية 80mm</span>
                            <span class="text-[11px] text-gray-500">المقاس القياسي لنقاط البيع</span>
                        </button>

                        <button type="button"
                                @click="paperWidth = '58mm'"
                                :class="paperWidth === '58mm'
                                    ? 'border-emerald-600 bg-emerald-50/60 text-emerald-900 ring-1 ring-emerald-600/20'
                                    : 'border-gray-200 bg-white text-slate-700 hover:border-gray-300 hover:bg-gray-50'"
                                class="p-4 rounded-2xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1.5">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors"
                                 :class="paperWidth === '58mm' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-slate-600'">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 2v20l2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1Z" />
                                    <path d="M9 7h6M9 11h6M9 15h4" />
                                </svg>
                            </div>
                            <span class="font-bold text-sm tabular-nums">طابعة حرارية 58mm</span>
                            <span class="text-[11px] text-gray-500">المقاس الصغير أو المحمول</span>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">عبارة أعلى الفاتورة</label>
                            <input type="text"
                                   name="header_note"
                                   value="{{ old('header_note', 'أهلاً بكم في متجرنا') }}"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-700">عبارة أسفل الفاتورة</label>
                            <input type="text"
                                   name="footer_note"
                                   value="{{ old('footer_note', 'شكراً لزيارتكم!') }}"
                                   class="w-full rounded-xl border border-gray-200 bg-gray-50/70 px-3.5 py-2.5 text-sm text-slate-900 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <button type="button"
                                @click="step = 2"
                                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-slate-700 text-sm font-semibold transition-colors cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m12 5 7 7-7 7" />
                                <path d="M5 12h14" />
                            </svg>
                            <span>السابق</span>
                        </button>

                        <button type="submit"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-xs transition-colors cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            <span>إنهاء التهيئة وبدء العمل</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
</body>
</html>