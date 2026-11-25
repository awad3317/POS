@extends('layouts.app')
@section('title', 'المنتجات والمخزن')
@section('header', 'إدارة المنتجات والباركود')
@section('breadcrumb', 'الرئيسية / المخزون والمنتجات / المنتجات والباركود')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-5" x-data="{ importModal: false }">

    {{-- Filter & Actions Toolbar --}}
    <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-xs sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form method="GET" action="{{ route('products.index') }}" class="flex flex-wrap items-center gap-2.5">
                <div class="relative">
                    <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو الباركود..."
                           class="h-10 w-56 rounded-lg border border-gray-300 bg-white pr-3.5 pl-3 text-xs text-gray-900 placeholder:text-gray-400 focus:border-emerald-500 focus:outline-none sm:w-64">
                </div>

                <select name="category_id"
                        class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 focus:border-emerald-500 focus:outline-none">
                    <option value="">كل التصنيفات</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected((int)$categoryId === $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>

                <select name="filter"
                        class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs font-medium text-gray-700 focus:border-emerald-500 focus:outline-none">
                    <option value="">كل الحالات</option>
                    <option value="low_stock" @selected($filter === 'low_stock')>النواقص فقط</option>
                    <option value="negative" @selected($filter === 'negative')>مخزون سالب</option>
                </select>

                <button type="submit"
                        class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-slate-900 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-slate-800">
                    <span>تصفية</span>
                </button>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('products.export-csv') }}"
                   class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>تصدير CSV</span>
                </a>

                <button @click="importModal = true" type="button"
                        class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span>استيراد CSV</span>
                </button>

                <a href="{{ route('products.create') }}"
                   class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>منتج جديد</span>
                </a>
            </div>
        </div>
    </section>

    {{-- Products Table Card --}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs sm:text-[13px]">
                <thead class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="whitespace-nowrap px-5 py-3.5">المنتج</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الباركود / النوع</th>
                        <th class="whitespace-nowrap px-5 py-3.5">التصنيف</th>
                        @can('products.view_cost')
                            <th class="whitespace-nowrap px-5 py-3.5">سعر الشراء</th>
                        @endcan
                        <th class="whitespace-nowrap px-5 py-3.5">سعر البيع</th>
                        <th class="whitespace-nowrap px-5 py-3.5">المخزون</th>
                        <th class="whitespace-nowrap px-5 py-3.5 text-left">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($products as $p)
                        <tr class="transition hover:bg-gray-50/70">
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-semibold text-gray-900">{{ $p->name }}</span>
                                    @if ($p->is_quick_pick)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                                            بيع سريع
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="font-mono text-xs text-gray-700" dir="ltr">{{ $p->barcode ?? '—' }}</div>
                                <div class="mt-1 flex items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-700">
                                        {{ product_type_label($p->type) }}
                                    </span>
                                    @if ($p->scale_plu_code)
                                        <span class="font-mono text-[11px] text-gray-400">PLU: {{ $p->scale_plu_code }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-gray-600">
                                {{ $p->category?->name ?? '—' }}
                            </td>
                            @can('products.view_cost')
                                <td class="whitespace-nowrap px-5 py-3.5 text-gray-700 tabular-nums">
                                    {{ format_money($p->purchase_price, $store->decimal_places) }}
                                    <span class="text-[11px] text-gray-400">{{ $sym }}</span>
                                </td>
                            @endcan
                            <td class="whitespace-nowrap px-5 py-3.5 font-bold text-emerald-700 tabular-nums">
                                {{ format_money($p->sale_price, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-emerald-600/70">{{ $sym }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 tabular-nums">
                                <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-bold {{ (float)$p->quantity <= (float)$p->min_stock_alert ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/15' : 'bg-gray-100 text-gray-800' }}">
                                    {{ format_qty($p->quantity) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-left">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('products.edit', $p->id) }}"
                                       class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50">
                                        تعديل
                                    </a>
                                    <a href="{{ route('products.stock-card', $p->id) }}"
                                       class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50">
                                        كرت الصنف
                                    </a>
                                    <form method="POST" action="{{ route('products.destroy', $p->id) }}" onsubmit="return confirm('أرشفة هذا المنتج؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg border border-rose-200 bg-rose-50/60 px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                            أرشفة
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-xs text-gray-400">
                                لا توجد منتجات مسجلة حالياً.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">
            {{ $products->links() }}
        </div>
    </section>

    {{-- Import CSV Modal --}}
    <div x-show="importModal" style="display: none;"
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 backdrop-blur-[1px]">
        <div @click.outside="importModal = false" class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h3 class="text-sm font-bold text-gray-900">استيراد منتجات عبر ملف CSV</h3>
                <button type="button" @click="importModal = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('products.import-csv') }}" enctype="multipart/form-data" class="space-y-4 p-5">
                @csrf
                <p class="text-xs leading-relaxed text-gray-500">
                    يطابق المنتجات بالباركود ويلزم وجود عمودَي <code class="rounded bg-gray-100 px-1 py-0.5 text-gray-800">purchase_price</code> و <code class="rounded bg-gray-100 px-1 py-0.5 text-gray-800">sale_price</code>.
                </p>
                <input type="file" name="csv_file" accept=".csv,text/csv" required
                       class="w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-xs text-gray-700">
                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <button type="button" @click="importModal = false"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        إلغاء
                    </button>
                    <button type="submit"
                            class="h-9 rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs hover:bg-emerald-700">
                        بدء الاستيراد
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection