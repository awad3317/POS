@extends('layouts.app')
@section('title', $product->exists ? 'تعديل منتج' : 'إضافة منتج')
@section('header', $product->exists ? 'تعديل المنتج: ' . $product->name : 'إضافة منتج جديد')
@section('content')
@php
    $flags = $store->feature_flags ?? [];
    $isEdit = $product->exists;
@endphp

<div class="max-w-4xl bg-white rounded-2xl border border-slate-200 p-6"
     x-data="{
        type: '{{ old('type', $product->type ?? 'simple') }}',
        barcode: '{{ old('barcode', $product->barcode ?? '') }}',
        name: '{{ old('name', $product->name ?? '') }}',
        catalogMsg: '',
        sizesInput: '',
        colorsInput: '',
        variants: @js(old('variants', $isEdit ? $product->variants->toArray() : [])),
        units: @js(old('units', $isEdit ? $product->units->toArray() : [])),
        async lookupBarcode() {
            if (!this.barcode) return;
            const res = await fetch(`{{ route('products.barcode-lookup') }}?barcode=${encodeURIComponent(this.barcode)}`);
            const data = await res.json();
            if (data.found) {
                if (!this.name) this.name = data.name_ar;
                this.catalogMsg = `تم العثور في الكتالوج اليمني: ${data.name_ar}`;
            } else {
                this.catalogMsg = '';
            }
        },
        generateMatrix() {
            const sizes = this.sizesInput.split(',').map(s => s.trim()).filter(Boolean);
            const colors = this.colorsInput.split(',').map(c => c.trim()).filter(Boolean);
            let seq = 1;
            this.variants = [];
            (sizes.length ? sizes : ['']).forEach(sz => {
                (colors.length ? colors : ['']).forEach(cl => {
                    if (!sz && !cl) return;
                    this.variants.push({
                        size: sz,
                        color: cl,
                        attribute_label: [sz, cl].filter(Boolean).join(' - '),
                        barcode: this.barcode ? `${this.barcode}-${seq++}` : '',
                        purchase_price: 0,
                        sale_price: 0,
                        quantity: 0
                    });
                });
            });
        },
        addUnit() {
            this.units.push({ unit_name: 'كرتون', conversion_factor: 24, barcode: '', purchase_price: 0, sale_price: 0 });
        }
     }">

    <form method="POST" action="{{ $isEdit ? route('products.update', $product->id) : route('products.store') }}" class="space-y-5">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold mb-1">الباركود (يمسح بالماسح)</label>
                <input type="text" name="barcode" x-model="barcode" @change="lookupBarcode()"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                <p x-show="catalogMsg" x-text="catalogMsg" class="text-[11px] text-emerald-600 mt-1 font-medium"></p>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold mb-1">اسم الصنف *</label>
                <input type="text" name="name" x-model="name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1">نوع الصنف</label>
                <select name="type" x-model="type" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    <option value="simple">صنف عادي / وحدات</option>
                    @if(!empty($flags['variants']) || $store->business_type === 'clothing')
                        <option value="variant">متعدد المقاسات والألوان</option>
                    @endif
                    @if(!empty($flags['scale_plu']) || $store->business_type === 'grocery')
                        <option value="weighted">صنف موزون بالميزان الإلكتروني</option>
                    @endif
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1">التصنيف</label>
                <select name="category_id" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    <option value="">— بدون تصنيف —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((int) old('category_id', $product->category_id) === $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1">رمز SKU (اختياري)</label>
                <input type="text" name="sku" value="{{ old('sku', $product->sku) }}" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1">سعر الشراء الأساسي *</label>
                <input type="number" step="0.0001" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price) }}" required
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1">سعر البيع *</label>
                <input type="number" step="0.0001" name="sale_price" value="{{ old('sale_price', $product->sale_price) }}" required
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>

            @if(!$isEdit)
            <div>
                <label class="block text-xs font-semibold mb-1">الرصيد الافتتاحي</label>
                <input type="number" step="0.001" name="quantity" value="{{ old('quantity', '0') }}"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>
            @endif

            <div>
                <label class="block text-xs font-semibold mb-1">حد تنبيه النواقص</label>
                <input type="number" step="0.001" name="min_stock_alert" value="{{ old('min_stock_alert', $product->min_stock_alert) }}"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>

            <div x-show="type === 'weighted'">
                <label class="block text-xs font-semibold mb-1">رقم الميزان (PLU من 5 أرقام)</label>
                <input type="text" name="scale_plu_code" value="{{ old('scale_plu_code', $product->scale_plu_code) }}"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="مثال: 10025">
            </div>
        </div>

        @if(!empty($flags['exchange_rate']) || $store->secondary_currency_code)
        <div class="p-4 rounded-xl bg-amber-50/60 border border-amber-200 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs font-semibold mb-1">عملة الشراء الأجنبية</label>
                <input type="text" name="cost_currency" value="{{ old('cost_currency', $product->cost_currency ?? $store->secondary_currency_code) }}" class="w-full border bg-white rounded-lg px-3 py-1.5 text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">تكلفة الشراء بالعملة الأجنبية</label>
                <input type="number" step="0.0001" name="foreign_cost_price" value="{{ old('foreign_cost_price', $product->foreign_cost_price) }}" class="w-full border bg-white rounded-lg px-3 py-1.5 text-xs">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">نسبة هامش الربح التلقائي (%)</label>
                <input type="number" step="0.01" name="profit_margin_percent" value="{{ old('profit_margin_percent', $product->profit_margin_percent) }}" class="w-full border bg-white rounded-lg px-3 py-1.5 text-xs">
            </div>
        </div>
        @endif

        <!-- Clothing Variants Matrix -->
        <div x-show="type === 'variant'" class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
            <div class="font-bold text-xs text-slate-800">مولّد المقاسات والألوان السريع</div>
            @if(!$isEdit)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <input type="text" x-model="sizesInput" placeholder="المقاسات: 40,41,42" class="border bg-white rounded-lg px-3 py-1.5 text-xs">
                <input type="text" x-model="colorsInput" placeholder="الألوان: أسود,بني" class="border bg-white rounded-lg px-3 py-1.5 text-xs">
                <button type="button" @click="generateMatrix()" class="px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-semibold">توليد المتغيرات</button>
            </div>
            @endif

            <template x-for="(v, idx) in variants" :key="idx">
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 bg-white p-2 rounded-lg border border-slate-200 text-xs">
                    <input type="text" :name="`variants[${idx}][attribute_label]`" x-model="v.attribute_label" placeholder="المقاس/اللون" class="border rounded px-2 py-1">
                    <input type="text" :name="`variants[${idx}][barcode]`" x-model="v.barcode" placeholder="باركود المتغير" class="border rounded px-2 py-1">
                    <input type="number" step="0.0001" :name="`variants[${idx}][purchase_price]`" x-model="v.purchase_price" placeholder="الشراء" class="border rounded px-2 py-1">
                    <input type="number" step="0.0001" :name="`variants[${idx}][sale_price]`" x-model="v.sale_price" placeholder="البيع" class="border rounded px-2 py-1">
                    <input type="number" step="0.001" :name="`variants[${idx}][quantity]`" x-model="v.quantity" placeholder="الكمية" class="border rounded px-2 py-1">
                </div>
            </template>
        </div>

        <!-- Grocery Multi-Units -->
        @if(!empty($flags['multi_units']) || $store->business_type === 'grocery')
        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
            <div class="flex items-center justify-between">
                <span class="font-bold text-xs text-slate-800">وحدات القياس الإضافية (باكت / كرتون / درزن)</span>
                <button type="button" @click="addUnit()" class="px-3 py-1 rounded-lg bg-slate-800 text-white text-xs">+ إضافة وحدة</button>
            </div>
            <template x-for="(u, idx) in units" :key="idx">
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 bg-white p-2 rounded-lg border border-slate-200 text-xs">
                    <input type="text" :name="`units[${idx}][unit_name]`" x-model="u.unit_name" placeholder="اسم الوحدة (كرتون)" class="border rounded px-2 py-1">
                    <input type="number" step="0.001" :name="`units[${idx}][conversion_factor]`" x-model="u.conversion_factor" placeholder="عدد الحبات (24)" class="border rounded px-2 py-1">
                    <input type="text" :name="`units[${idx}][barcode]`" x-model="u.barcode" placeholder="باركود الوحدة" class="border rounded px-2 py-1">
                    <input type="number" step="0.0001" :name="`units[${idx}][purchase_price]`" x-model="u.purchase_price" placeholder="شراء الوحدة" class="border rounded px-2 py-1">
                    <input type="number" step="0.0001" :name="`units[${idx}][sale_price]`" x-model="u.sale_price" placeholder="بيع الوحدة" class="border rounded px-2 py-1">
                </div>
            </template>
        </div>
        @endif

        <div class="flex flex-wrap gap-6 text-xs">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_quick_pick" value="1" @checked(old('is_quick_pick', $product->is_quick_pick))>
                <span>إظهار في أزرار البيع السريع بلا باركود (خبز / ماء)</span>
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))>
                <span>الصنف نشط للبيع</span>
            </label>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
            <a href="{{ route('products.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold">إلغاء</a>
            <button type="submit" class="px-6 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">حفظ المنتج</button>
        </div>
    </form>
</div>
@endsection
