@extends('layouts.app')
@section('title', 'الفواتير والمرتجعات')
@section('header', 'سجل الفواتير وسندات المرتجع')
@section('breadcrumb', 'الرئيسية / الفواتير والمرتجعات')

@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="space-y-5">
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
            <div>
                <h2 class="text-sm font-bold text-gray-900 sm:text-base">سجل الفواتير وسندات المرتجع</h2>
                <p class="mt-0.5 text-xs text-gray-500">دفتر مالي محمي غير قابل للحذف</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs sm:text-[13px]">
                <thead class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="whitespace-nowrap px-5 py-3.5">رقم الفاتورة</th>
                        <th class="whitespace-nowrap px-5 py-3.5">النوع / الحالة</th>
                        <th class="whitespace-nowrap px-5 py-3.5">العميل</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الكاشير</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الإجمالي</th>
                        <th class="whitespace-nowrap px-5 py-3.5">المدفوع / الآجل</th>
                        <th class="whitespace-nowrap px-5 py-3.5">التاريخ</th>
                        <th class="whitespace-nowrap px-5 py-3.5 text-left">عرض</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $o)
                        <tr class="transition hover:bg-gray-50/70">
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs font-bold text-gray-900" dir="ltr">
                                {{ $o->invoice_number }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $o->type === 'return' ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' }}">
                                    {{ $o->type === 'return' ? 'مرتجع' : 'مبيعات' }} ({{ $o->status }})
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-medium text-gray-800">
                                {{ $o->customer?->name ?? 'عميل نقدي' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-gray-600">
                                {{ $o->user?->name }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-bold text-gray-900 tabular-nums">
                                {{ format_money($o->total_amount, $store->decimal_places) }}
                                <span class="text-[11px] font-medium text-gray-400">{{ $sym }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 tabular-nums">
                                <span class="font-semibold text-emerald-700">{{ format_money($o->paid_amount, $store->decimal_places) }}</span>
                                <span class="mx-1 text-gray-300">/</span>
                                <span class="font-semibold text-rose-600">{{ format_money($o->credit_amount, $store->decimal_places) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-gray-500 tabular-nums" dir="ltr">
                                {{ $o->sold_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-left">
                                <a href="{{ route('orders.show', $o->id) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">
                                    <span>التفاصيل / مرتجع</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-xs text-gray-400">
                                لا توجد فواتير مسجلة حتى الآن.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">
            {{ $orders->links() }}
        </div>
    </section>
</div>
@endsection