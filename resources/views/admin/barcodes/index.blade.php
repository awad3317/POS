@extends('layouts.app')
@section('title', 'طباعة استيكرات الباركود')
@section('header', 'مصمم وطباعة ملصقات الباركود الحرارية و A4')

@section('vite_entry')
    @vite(['resources/css/app.css', 'resources/js/barcodes.js'])
@endsection

@section('content')
@php
    $barcodeConfig = [
        'store' => [
            'id' => $store->id,
            'name' => $store->name,
            'currency_symbol' => $store->currency_symbol,
            'decimal_places' => $store->decimal_places,
        ],
        'searchUrl' => route('barcodes.search'),
        'initialItems' => $initialItems,
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start"
     x-data="barcodeLabelsApp(@js($barcodeConfig))"
     x-init="init()">

    <!-- Search & Catalog Column -->
    <div class="lg:col-span-5 rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-7 h-7 rounded-lg bg-slate-900 text-white text-xs font-bold flex items-center justify-center shrink-0 tabular-nums">1</span>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-900 truncate">ابحث عن الأصناف أو المتغيرات</h2>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600 shrink-0 tabular-nums">
                حد أقصى 20 نتيجة
            </span>
        </div>

        <div class="p-5 space-y-4">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                    </span>
                    <input type="text"
                           x-model="query"
                           @input="onSearchInput()"
                           placeholder="اسم الصنف، الباركود، المقاس/اللون، أو SKU..."
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 pr-9 pl-3 py-2.5 text-xs text-slate-900 placeholder:text-gray-400 focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                </div>
                <button type="button"
                        @click="fetchSearch()"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold shrink-0 transition-colors cursor-pointer">
                    <svg x-show="loading" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span x-show="!loading">بحث</span>
                    <span x-show="loading">جارٍ البحث</span>
                </button>
            </div>

            <div class="divide-y divide-gray-100 max-h-[540px] overflow-y-auto -mx-2 px-2">
                <template x-for="item in searchItems" :key="item.key">
                    <div class="py-3 px-2 -mx-2 rounded-xl hover:bg-gray-50/90 transition-colors flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-xs font-bold text-slate-900 truncate" x-text="item.name"></span>
                                <span x-show="item.subtitle"
                                      class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 shrink-0"
                                      x-text="item.subtitle"></span>
                            </div>
                            <div class="flex items-center gap-2 text-[11px] text-gray-500">
                                <span class="inline-flex items-center gap-1 font-mono text-gray-600 tabular-nums">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14" />
                                    </svg>
                                    <span x-text="item.barcode"></span>
                                </span>
                                <span class="text-gray-300">•</span>
                                <strong class="font-bold text-emerald-700 tabular-nums" x-text="fmtPrice(item.price)"></strong>
                            </div>
                        </div>

                        <button type="button"
                                @click="addLabel(item, 1)"
                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-600 hover:border-emerald-600 text-emerald-700 hover:text-white text-xs font-semibold shrink-0 transition-colors cursor-pointer">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            <span>إضافة</span>
                        </button>
                    </div>
                </template>

                <div x-show="searchItems.length === 0" class="py-12 text-center">
                    <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 5v14M8 5v14M12 5v14M17 5v14M21 5v14" />
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-gray-500">لا توجد أصناف بباركود مطابقة للبحث.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Label Settings, Queue & Preview Column -->
    <div class="lg:col-span-7 space-y-5">

        <!-- Print Options Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-slate-900 text-white text-xs font-bold flex items-center justify-center shrink-0 tabular-nums">2</span>
                    <h2 class="text-sm font-bold text-slate-900">مقاس الملصق وخيارات العرض</h2>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200/70 text-[11px] font-mono font-semibold uppercase"
                      x-text="paperSize"></span>
            </div>

            <div class="p-5 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <button type="button"
                            @click="paperSize = '50x25mm'"
                            :class="paperSize === '50x25mm'
                                ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 ring-1 ring-emerald-600/20'
                                : 'border-gray-200 bg-white text-slate-700 hover:border-gray-300 hover:bg-gray-50'"
                            class="p-3 rounded-xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1">
                        <svg class="w-4 h-4 opacity-75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="6" width="18" height="12" rx="2" />
                            <path d="M7 10v4M10 10v4M14 10v4M17 10v4" />
                        </svg>
                        <span class="text-xs font-bold tabular-nums">50×25mm</span>
                        <span class="text-[10px] text-gray-500">رول حراري</span>
                    </button>

                    <button type="button"
                            @click="paperSize = '40x30mm'"
                            :class="paperSize === '40x30mm'
                                ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 ring-1 ring-emerald-600/20'
                                : 'border-gray-200 bg-white text-slate-700 hover:border-gray-300 hover:bg-gray-50'"
                            class="p-3 rounded-xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1">
                        <svg class="w-4 h-4 opacity-75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="5" width="16" height="14" rx="2" />
                            <path d="M8 9v6M11 9v6M14 9v6M16 9v6" />
                        </svg>
                        <span class="text-xs font-bold tabular-nums">40×30mm</span>
                        <span class="text-[10px] text-gray-500">رول حراري</span>
                    </button>

                    <button type="button"
                            @click="paperSize = '38x25mm'"
                            :class="paperSize === '38x25mm'
                                ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 ring-1 ring-emerald-600/20'
                                : 'border-gray-200 bg-white text-slate-700 hover:border-gray-300 hover:bg-gray-50'"
                            class="p-3 rounded-xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1">
                        <svg class="w-4 h-4 opacity-75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="6" width="14" height="12" rx="2" />
                            <path d="M8 10v4M11 10v4M13 10v4M16 10v4" />
                        </svg>
                        <span class="text-xs font-bold tabular-nums">38×25mm</span>
                        <span class="text-[10px] text-gray-500">رول حراري</span>
                    </button>

                    <button type="button"
                            @click="paperSize = 'a4'"
                            :class="paperSize === 'a4'
                                ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 ring-1 ring-emerald-600/20'
                                : 'border-gray-200 bg-white text-slate-700 hover:border-gray-300 hover:bg-gray-50'"
                            class="p-3 rounded-xl border text-center transition-all cursor-pointer flex flex-col items-center gap-1">
                        <svg class="w-4 h-4 opacity-75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1" />
                            <rect x="14" y="3" width="7" height="7" rx="1" />
                            <rect x="3" y="14" width="7" height="7" rx="1" />
                            <rect x="14" y="14" width="7" height="7" rx="1" />
                        </svg>
                        <span class="text-xs font-bold">ورق A4</span>
                        <span class="text-[10px] text-gray-500">شبكة ملصقات</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-3 border-t border-gray-100">
                    <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-gray-50 text-xs font-semibold text-slate-700 cursor-pointer transition-colors">
                        <input type="checkbox"
                               x-model="showStoreName"
                               class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                        <span>إظهار اسم المتجر</span>
                    </label>

                    <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-gray-50 text-xs font-semibold text-slate-700 cursor-pointer transition-colors">
                        <input type="checkbox"
                               x-model="showProductName"
                               class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                        <span>إظهار اسم الصنف / المقاس</span>
                    </label>

                    <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border border-gray-200 bg-gray-50/50 hover:bg-gray-50 text-xs font-semibold text-slate-700 cursor-pointer transition-colors">
                        <input type="checkbox"
                               x-model="showPrice"
                               class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600">
                        <span>إظهار السعر</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Selected Stickers Queue -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-slate-900 text-white text-xs font-bold flex items-center justify-center shrink-0 tabular-nums">3</span>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-slate-900">قائمة الطباعة</h2>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-slate-700 tabular-nums">
                            <span x-text="totalStickerCount()"></span>
                            <span class="mr-1 font-medium text-gray-500">ملصق</span>
                        </span>
                    </div>
                </div>

                <button type="button"
                        @click="printLabels()"
                        :disabled="selectedLabels.length === 0"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 9V2h12v7" />
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                        <rect x="6" y="14" width="12" height="8" />
                    </svg>
                    <span>طباعة الملصقات فوراً</span>
                </button>
            </div>

            <div class="px-5 py-2">
                <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                    <template x-for="(lbl, idx) in selectedLabels" :key="lbl.key">
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-bold text-slate-900 truncate"
                                     x-text="lbl.subtitle ? `${lbl.name} (${lbl.subtitle})` : lbl.name"></div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-gray-500 tabular-nums">
                                    <span class="font-mono" x-text="lbl.barcode"></span>
                                    <span class="text-gray-300">•</span>
                                    <span class="font-semibold text-emerald-700" x-text="fmtPrice(lbl.price)"></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <label class="text-[11px] font-medium text-gray-500">العدد:</label>
                                <input type="number"
                                       min="1"
                                       max="500"
                                       x-model.number="lbl.copies"
                                       class="w-20 text-center rounded-xl border border-gray-200 bg-gray-50/70 py-1.5 text-xs font-bold text-slate-900 tabular-nums focus:bg-white focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/15 focus:outline-none transition-colors">
                                <button type="button"
                                        @click="removeLabel(idx)"
                                        title="حذف من القائمة"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 6 6 18M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div x-show="selectedLabels.length === 0" class="py-10 text-center">
                        <div class="w-11 h-11 rounded-2xl bg-gray-100 text-gray-400 mx-auto mb-3 flex items-center justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9V2h12v7" />
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                <rect x="6" y="14" width="12" height="8" />
                            </svg>
                        </div>
                        <p class="text-xs font-medium text-gray-500">اختر أصنافاً من القائمة لإضافتها إلى دفعة الطباعة.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Visual Preview -->
        <div x-show="selectedLabels.length > 0"
             class="rounded-2xl border border-dashed border-gray-300 bg-[#F9FAFB] p-5 space-y-3.5">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <h3 class="text-xs font-bold text-slate-800">معاينة شكل الملصق</h3>
                    <span class="text-[11px] text-gray-500">(أول 4 أصناف)</span>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg bg-white border border-gray-200 text-[11px] font-mono font-semibold text-slate-700 uppercase"
                      x-text="paperSize"></span>
            </div>

            <div class="flex flex-wrap gap-3">
                <template x-for="lbl in selectedLabels.slice(0, 4)" :key="`prev-${lbl.key}`">
                    <div class="bg-white border border-gray-300 rounded-xl p-3 text-center shadow-xs w-44 flex flex-col items-center justify-center">
                        <div x-show="showStoreName"
                             class="text-[10px] font-bold text-slate-800 truncate w-full"
                             x-text="store.name"></div>
                        <div x-show="showProductName"
                             class="text-[11px] font-semibold text-slate-700 truncate w-full mt-0.5"
                             x-text="lbl.subtitle ? `${lbl.name} (${lbl.subtitle})` : lbl.name"></div>
                        <div class="my-1.5" x-html="renderPreviewSvg(lbl.barcode)"></div>
                        <div x-show="showPrice"
                             class="text-xs font-extrabold text-slate-900 tabular-nums"
                             x-text="fmtPrice(lbl.price)"></div>
                    </div>
                </template>
            </div>
        </div>

    </div>
</div>
@endsection