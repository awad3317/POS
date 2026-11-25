@extends('layouts.app')
@section('title', $product->exists ? 'تعديل منتج' : 'إضافة منتج')
@section('header', $product->exists ? 'تعديل المنتج: ' . $product->name : 'إضافة منتج جديد')
@section('breadcrumb', 'الرئيسية / المنتجات والباركود / ' . ($product->exists ? 'تعديل منتج' : 'إضافة منتج'))

@section('content')
@php
    $flags = $store->feature_flags ?? [];
    $isEdit = $product->exists;
    $sym = $store->currency_symbol;
    $initialVariants = old('variants', $isEdit ? $product->variants->map(fn ($v) => [
        'size' => $v->size,
        'color' => $v->color,
        'attribute_label' => $v->attribute_label,
        'barcode' => $v->barcode,
        'purchase_price' => format_input($v->purchase_price),
        'sale_price' => format_input($v->sale_price),
        'quantity' => format_qty($v->quantity),
    ])->all() : []);
    $initialUnits = old('units', $isEdit ? $product->units->map(fn ($u) => [
        'unit_name' => $u->unit_name,
        'conversion_factor' => format_qty($u->conversion_factor),
        'barcode' => $u->barcode,
        'purchase_price' => format_input($u->purchase_price),
        'sale_price' => format_input($u->sale_price),
        'is_default' => $u->is_default,
    ])->all() : []);
@endphp

<div x-data="{
        type: '{{ old('type', $product->type ?? 'simple') }}',
        barcode: '{{ old('barcode', $product->barcode ?? '') }}',
        name: '{{ old('name', $product->name ?? '') }}',
        catalogMsg: '',
        sizesInput: '',
        colorsInput: '',
        variants: @js($initialVariants),
        units: @js($initialUnits),
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

    <form method="POST" action="{{ $isEdit ? route('products.update', $product->id) : route('products.store') }}">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

            {{-- Main Column (8 cols) --}}
            <div class="space-y-6 lg:col-span-8">

                {{-- Card 1: Basic Info --}}
                <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                        <h2 class="text-sm font-bold text-gray-900 sm:text-base">البيانات الأساسية للصنف</h2>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3 sm:p-6">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">الباركود (يمسح بالماسح)</label>
                            <input type="text" name="barcode" x-model="barcode" @change="lookupBarcode()"
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm text-gray-900 focus:border-emerald-500 focus:outline-none">
                            <p x-show="catalogMsg" x-text="catalogMsg" class="mt-1 text-[11px] font-medium text-emerald-600"></p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">اسم الصنف *</label>
                            <input type="text" name="name" x-model="name" required
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm text-gray-900 focus:border-emerald-500 focus:outline-none">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">نوع الصنف</label>
                            <select name="type" x-model="type"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-emerald-500 focus:outline-none">
                                <option value="simple">عادي (حبة / وحدات)</option>
                                @if (!empty($flags['variants']) || $store->business_type === 'clothing')
                                    <option value="variant">متغيرات (مقاسات وألوان)</option>
                                @endif
                                @if (!empty($flags['scale_plu']) || $store->business_type === 'grocery')
                                    <option value="weighted">موزون (بالميزان الإلكتروني)</option>
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">رمز SKU (اختياري)</label>
                            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}"
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm text-gray-900 focus:border-emerald-500 focus:outline-none">
                        </div>

                        <div x-show="type === 'weighted'" style="display: none;">
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">رقم الميزان (PLU من 5 أرقام)</label>
                            <input type="text" name="scale_plu_code" value="{{ old('scale_plu_code', $product->scale_plu_code) }}"
                                   placeholder="مثال: 10025"
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm text-gray-900 focus:border-emerald-500 focus:outline-none">
                        </div>
                    </div>
                </section>

                {{-- Card 2: Pricing & Foreign Currency --}}
                <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                        <h2 class="text-sm font-bold text-gray-900 sm:text-base">التسعير والعملة ({{ $sym }})</h2>
                    </div>

                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">سعر الشراء الأساسي *</label>
                                <input type="number" step="any" name="purchase_price"
                                       value="{{ format_input(old('purchase_price', $product->purchase_price)) }}" required
                                       class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm font-semibold text-gray-900 tabular-nums focus:border-emerald-500 focus:outline-none">
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">سعر البيع *</label>
                                <input type="number" step="any" name="sale_price"
                                       value="{{ format_input(old('sale_price', $product->sale_price)) }}" required
                                       class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm font-semibold text-emerald-700 tabular-nums focus:border-emerald-500 focus:outline-none">
                            </div>
                        </div>

                        @if (!empty($flags['exchange_rate']) || $store->secondary_currency_code)
                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                                <div class="mb-3 text-xs font-bold text-amber-900">التسعير بعملة الشراء الأجنبية (اختياري)</div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-amber-900/80">عملة الشراء الأجنبية</label>
                                        <input type="text" name="cost_currency"
                                               value="{{ old('cost_currency', $product->cost_currency ?? $store->secondary_currency_code) }}"
                                               class="h-9 w-full rounded-lg border border-amber-300 bg-white px-3 text-xs text-gray-900 focus:border-amber-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-amber-900/80">تكلفة الشراء بالعملة الأجنبية</label>
                                        <input type="number" step="any" name="foreign_cost_price"
                                               value="{{ format_input(old('foreign_cost_price', $product->foreign_cost_price), true) }}"
                                               class="h-9 w-full rounded-lg border border-amber-300 bg-white px-3 text-xs tabular-nums text-gray-900 focus:border-amber-500 focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-amber-900/80">نسبة هامش الربح التلقائي (%)</label>
                                        <input type="number" step="any" name="profit_margin_percent"
                                               value="{{ format_input(old('profit_margin_percent', $product->profit_margin_percent), true) }}"
                                               class="h-9 w-full rounded-lg border border-amber-300 bg-white px-3 text-xs tabular-nums text-gray-900 focus:border-amber-500 focus:outline-none">
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Card 3: Variants Matrix --}}
                <section x-show="type === 'variant'" style="display: none;" class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                        <h2 class="text-sm font-bold text-gray-900 sm:text-base">مولّد المقاسات والألوان السريع</h2>
                    </div>

                    <div class="space-y-4 p-5 sm:p-6">
                        @if (!$isEdit)
                            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                                <input type="text" x-model="sizesInput" placeholder="المقاسات: 40,41,42"
                                       class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-emerald-500 focus:outline-none">
                                <input type="text" x-model="colorsInput" placeholder="الألوان: أسود,بني"
                                       class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs text-gray-900 focus:border-emerald-500 focus:outline-none">
                                <button type="button" @click="generateMatrix()"
                                        class="h-10 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white transition hover:bg-slate-800">
                                    توليد المتغيرات
                                </button>
                            </div>
                        @endif

                        <div class="space-y-2">
                            <template x-for="(v, idx) in variants" :key="idx">
                                <div class="grid grid-cols-2 gap-2 rounded-xl border border-gray-200 bg-gray-50/70 p-2.5 text-xs sm:grid-cols-5">
                                    <input type="text" :name="`variants[${idx}][attribute_label]`" x-model="v.attribute_label" placeholder="المقاس/اللون"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="text" :name="`variants[${idx}][barcode]`" x-model="v.barcode" placeholder="باركود المتغير"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 font-mono text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="number" step="any" :name="`variants[${idx}][purchase_price]`" x-model="v.purchase_price" placeholder="الشراء"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="number" step="any" :name="`variants[${idx}][sale_price]`" x-model="v.sale_price" placeholder="البيع"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="number" step="any" :name="`variants[${idx}][quantity]`" x-model="v.quantity" placeholder="الكمية"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                {{-- Card 4: Multi-Units --}}
                @if (!empty($flags['multi_units']) || $store->business_type === 'grocery')
                    <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
                            <h2 class="text-sm font-bold text-gray-900 sm:text-base">وحدات القياس الإضافية (باكت / كرتون / درزن)</h2>
                            <button type="button" @click="addUnit()"
                                    class="inline-flex h-8 items-center rounded-lg bg-slate-900 px-3 text-xs font-bold text-white transition hover:bg-slate-800">
                                + إضافة وحدة
                            </button>
                        </div>

                        <div class="space-y-2 p-5 sm:p-6">
                            <template x-for="(u, idx) in units" :key="idx">
                                <div class="grid grid-cols-2 gap-2 rounded-xl border border-gray-200 bg-gray-50/70 p-2.5 text-xs sm:grid-cols-5">
                                    <input type="text" :name="`units[${idx}][unit_name]`" x-model="u.unit_name" placeholder="اسم الوحدة (كرتون)"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="number" step="any" :name="`units[${idx}][conversion_factor]`" x-model="u.conversion_factor" placeholder="عدد الحبات (24)"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="text" :name="`units[${idx}][barcode]`" x-model="u.barcode" placeholder="باركود الوحدة"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 font-mono text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="number" step="any" :name="`units[${idx}][purchase_price]`" x-model="u.purchase_price" placeholder="شراء الوحدة"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                                    <input type="number" step="any" :name="`units[${idx}][sale_price]`" x-model="u.sale_price" placeholder="بيع الوحدة"
                                           class="h-9 rounded-lg border border-gray-300 bg-white px-2.5 tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                                </div>
                            </template>
                        </div>
                    </section>
                @endif

            </div>

            {{-- Sidebar Column (4 cols) --}}
            <div class="space-y-6 lg:col-span-4">

                {{-- Classification & Stock --}}
                <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-sm font-bold text-gray-900">التصنيف والمخزون</h2>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">التصنيف</label>
                            <select name="category_id"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-emerald-500 focus:outline-none">
                                <option value="">— بدون تصنيف —</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" @selected((int) old('category_id', $product->category_id) === $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if (!$isEdit)
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-700">الرصيد الافتتاحي</label>
                                <input type="number" step="any" name="quantity" value="{{ format_qty(old('quantity', '0')) }}"
                                       class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                            </div>
                        @endif

                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700">حد تنبيه النواقص</label>
                            <input type="number" step="any" name="min_stock_alert" value="{{ format_qty(old('min_stock_alert', $product->min_stock_alert)) }}"
                                   class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-sm tabular-nums text-gray-900 focus:border-emerald-500 focus:outline-none">
                        </div>
                    </div>
                </section>

                {{-- Display Options & Save --}}
                <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-sm font-bold text-gray-900">خيارات البيع والحفظ</h2>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="space-y-3 text-xs">
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50/70 p-3 font-medium text-gray-800">
                                <input type="checkbox" name="is_active" value="1"
                                       class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                       @checked(old('is_active', $product->is_active ?? true))>
                                <span>الصنف نشط للبيع</span>
                            </label>

                            <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50/70 p-3 font-medium text-gray-800">
                                <input type="checkbox" name="is_quick_pick" value="1"
                                       class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                       @checked(old('is_quick_pick', $product->is_quick_pick))>
                                <span>إظهار في أزرار البيع السريع بلا باركود (خبز / ماء)</span>
                            </label>
                        </div>

                        <div class="flex flex-col gap-2 border-t border-gray-100 pt-4">
                            <button type="submit"
                                    class="h-10 w-full rounded-lg bg-emerald-600 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700">
                                حفظ المنتج
                            </button>
                            <a href="{{ route('products.index') }}"
                               class="flex h-10 w-full items-center justify-center rounded-lg border border-gray-200 bg-white text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                إلغاء والعودة
                            </a>
                        </div>
                    </div>
                </section>

            </div>

        </div>
    </form>
</div>
@endsection