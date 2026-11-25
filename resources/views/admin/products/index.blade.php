@extends('layouts.app')
@section('title', 'المنتجات والمخزن')
@section('header', 'إدارة المنتجات والباركود')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4" x-data="{ importModal: false }">
    <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو الباركود..."
               class="border border-slate-300 bg-white rounded-xl px-3 py-2 text-xs w-56">
        <select name="category_id" class="border border-slate-300 bg-white rounded-xl px-3 py-2 text-xs">
            <option value="">كل التصنيفات</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected((int)$categoryId === $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <select name="filter" class="border border-slate-300 bg-white rounded-xl px-3 py-2 text-xs">
            <option value="">كل الحالات</option>
            <option value="low_stock" @selected($filter === 'low_stock')>النواقص فقط</option>
            <option value="negative" @selected($filter === 'negative')>مخزون سالب</option>
        </select>
        <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold">تصفية</button>
    </form>

    <div class="flex items-center gap-2">
        <a href="{{ route('products.export-csv') }}" class="px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold">تصدير CSV</a>
        <button @click="importModal = true" type="button" class="px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold">استيراد CSV</button>
        <a href="{{ route('products.create') }}" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold">+ منتج جديد</a>
    </div>

    <!-- Import CSV Modal -->
    <div x-show="importModal" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <div @click.outside="importModal = false" class="bg-white rounded-2xl max-w-md w-full p-6">
            <h3 class="font-bold text-sm mb-2">استيراد منتجات عبر ملف CSV</h3>
            <p class="text-xs text-slate-500 mb-4">يطابق المنتجات بالباركود ويلزم وجود عمودَي `purchase_price` و `sale_price`.</p>
            <form method="POST" action="{{ route('products.import-csv') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full text-xs border border-slate-300 rounded-xl p-2">
                <div class="flex justify-end gap-2">
                    <button type="button" @click="importModal = false" class="px-3 py-1.5 rounded-lg border text-xs">إلغاء</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-bold">بدء الاستيراد</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-xs text-right">
            <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                <tr>
                    <th class="p-3">المنتج</th>
                    <th class="p-3">الباركود / النوع</th>
                    <th class="p-3">التصنيف</th>
                    @can('products.view_cost')
                    <th class="p-3">سعر الشراء</th>
                    @endcan
                    <th class="p-3">سعر البيع</th>
                    <th class="p-3">المخزون</th>
                    <th class="p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($products as $p)
                <tr>
                    <td class="p-3 font-semibold text-slate-900">
                        {{ $p->name }}
                        @if($p->is_quick_pick)
                            <span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px]">بيع سريع</span>
                        @endif
                    </td>
                    <td class="p-3 text-slate-600">
                        <div>{{ $p->barcode ?? '—' }}</div>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-semibold">
                                {{ product_type_label($p->type) }}
                            </span>
                            @if($p->scale_plu_code)
                                <span class="text-[11px] text-slate-400">PLU: {{ $p->scale_plu_code }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="p-3">{{ $p->category?->name ?? '—' }}</td>
                    @can('products.view_cost')
                    <td class="p-3">{{ format_money($p->purchase_price, $store->decimal_places) }} {{ $sym }}</td>
                    @endcan
                    <td class="p-3 font-bold text-emerald-700">{{ format_money($p->sale_price, $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-3 font-bold {{ (float)$p->quantity <= (float)$p->min_stock_alert ? 'text-rose-600' : 'text-slate-800' }}">
                        {{ format_qty($p->quantity) }}
                    </td>
                    <td class="p-3 flex items-center gap-2">
                        <a href="{{ route('products.edit', $p->id) }}" class="text-emerald-600 font-semibold">تعديل</a>
                        <a href="{{ route('products.stock-card', $p->id) }}" class="text-slate-600">كرت الصنف</a>
                        <form method="POST" action="{{ route('products.destroy', $p->id) }}" onsubmit="return confirm('أرشفة هذا المنتج؟')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-rose-600">أرشفة</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="p-6 text-center text-slate-400">لا توجد منتجات مسجلة حالياً.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3 border-t border-slate-100">{{ $products->links() }}</div>
</div>
@endsection
