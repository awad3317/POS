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

<div class="grid grid-cols-1 lg:grid-cols-12 gap-5 text-xs"
     x-data="barcodeLabelsApp(@js($barcodeConfig))"
     x-init="init()">

    <!-- Search & Catalog Column -->
    <div class="lg:col-span-5 bg-white rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-bold text-sm text-slate-900">1. ابحث عن الأصناف أو المتغيرات</h2>
            <span class="text-[11px] text-slate-400">بحد أقصى 20 نتيجة</span>
        </div>

        <div class="flex gap-2">
            <input type="text" x-model="query" @input="onSearchInput()"
                   placeholder="اسم الصنف، الباركود، المقاس/اللون، أو SKU..."
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none">
            <button type="button" @click="fetchSearch()" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white font-bold shrink-0">
                <span x-show="!loading">بحث</span>
                <span x-show="loading">...</span>
            </button>
        </div>

        <div class="divide-y divide-slate-100 max-h-[520px] overflow-y-auto">
            <template x-for="item in searchItems" :key="item.key">
                <div class="py-2.5 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-bold text-slate-900 truncate" x-text="item.name"></div>
                        <div class="text-[11px] text-slate-500">
                            <span x-show="item.subtitle" class="ml-1 font-semibold text-indigo-700" x-text="item.subtitle"></span>
                            <span class="font-mono" x-text="item.barcode"></span>
                            • <strong class="text-emerald-700" x-text="fmtPrice(item.price)"></strong>
                        </div>
                    </div>
                    <button type="button" @click="addLabel(item, 1)"
                            class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold shrink-0">
                        + إضافة
                    </button>
                </div>
            </template>
            <div x-show="searchItems.length === 0" class="py-8 text-center text-slate-400">
                لا توجد أصناف بباركود مطابقة للبحث.
            </div>
        </div>
    </div>

    <!-- Label Settings, Queue & Preview Column -->
    <div class="lg:col-span-7 space-y-4">
        <!-- Print Options Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 space-y-3">
            <h2 class="font-bold text-sm text-slate-900">2. مقاس الملصق وخيارات العرض</h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <button type="button" @click="paperSize = '50x25mm'"
                        :class="paperSize === '50x25mm' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-200'"
                        class="p-2.5 rounded-xl border-2 font-bold text-center">رول 50×25mm</button>
                <button type="button" @click="paperSize = '40x30mm'"
                        :class="paperSize === '40x30mm' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-200'"
                        class="p-2.5 rounded-xl border-2 font-bold text-center">رول 40×30mm</button>
                <button type="button" @click="paperSize = '38x25mm'"
                        :class="paperSize === '38x25mm' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-200'"
                        class="p-2.5 rounded-xl border-2 font-bold text-center">رول 38×25mm</button>
                <button type="button" @click="paperSize = 'a4'"
                        :class="paperSize === 'a4' ? 'border-emerald-600 bg-emerald-50 text-emerald-900' : 'border-slate-200'"
                        class="p-2.5 rounded-xl border-2 font-bold text-center">ورق A4 (شبكة)</button>
            </div>

            <div class="flex flex-wrap gap-4 pt-1">
                <label class="inline-flex items-center gap-1.5 font-semibold">
                    <input type="checkbox" x-model="showStoreName" class="rounded border-slate-300 text-emerald-600">
                    <span>إظهار اسم المتجر</span>
                </label>
                <label class="inline-flex items-center gap-1.5 font-semibold">
                    <input type="checkbox" x-model="showProductName" class="rounded border-slate-300 text-emerald-600">
                    <span>إظهار اسم الصنف / المقاس</span>
                </label>
                <label class="inline-flex items-center gap-1.5 font-semibold">
                    <input type="checkbox" x-model="showPrice" class="rounded border-slate-300 text-emerald-600">
                    <span>إظهار السعر</span>
                </label>
            </div>
        </div>

        <!-- Selected Stickers Queue -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-sm text-slate-900">
                    3. قائمة الطباعة (<span x-text="totalStickerCount()"></span> ملصق)
                </h2>
                <button type="button" @click="printLabels()" :disabled="selectedLabels.length === 0"
                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold shadow-sm">
                    🖨️ طباعة الملصقات فوراً
                </button>
            </div>

            <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                <template x-for="(lbl, idx) in selectedLabels" :key="lbl.key">
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-slate-900 truncate" x-text="lbl.subtitle ? `${lbl.name} (${lbl.subtitle})` : lbl.name"></div>
                            <div class="text-[11px] text-slate-500" x-text="`${lbl.barcode} • ${fmtPrice(lbl.price)}`"></div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <label class="text-[11px] text-slate-500">العدد:</label>
                            <input type="number" min="1" max="500" x-model.number="lbl.copies"
                                   class="w-16 text-center rounded-lg border border-slate-300 py-1 font-bold">
                            <button type="button" @click="removeLabel(idx)" class="text-rose-600 font-bold px-2">✕</button>
                        </div>
                    </div>
                </template>
                <div x-show="selectedLabels.length === 0" class="py-6 text-center text-slate-400">
                    اختر أصنافاً من القائمة لإضافتها إلى دفعة الطباعة.
                </div>
            </div>
        </div>

        <!-- Live Visual Preview -->
        <div x-show="selectedLabels.length > 0" class="bg-slate-50 rounded-2xl border border-dashed border-slate-300 p-4">
            <div class="text-[11px] font-bold text-slate-500 mb-2">معاينة شكل الملصق (<span x-text="paperSize"></span>):</div>
            <div class="flex flex-wrap gap-3">
                <template x-for="lbl in selectedLabels.slice(0, 4)" :key="`prev-${lbl.key}`">
                    <div class="bg-white border border-slate-300 rounded-lg p-2 text-center shadow-xs w-44 flex flex-col items-center justify-center">
                        <div x-show="showStoreName" class="text-[10px] font-bold text-slate-800 truncate w-full" x-text="store.name"></div>
                        <div x-show="showProductName" class="text-[11px] font-semibold text-slate-700 truncate w-full" x-text="lbl.subtitle ? `${lbl.name} (${lbl.subtitle})` : lbl.name"></div>
                        <div class="my-1" x-html="renderPreviewSvg(lbl.barcode)"></div>
                        <div x-show="showPrice" class="text-xs font-extrabold text-slate-900" x-text="fmtPrice(lbl.price)"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
