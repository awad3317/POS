@extends('layouts.app')
@section('title', 'الفواتير والمرتجعات')
@section('header', 'سجل الفواتير وسندات المرتجع (دفتر غير قابل للحذف)')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-xs text-right">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="p-3">رقم الفاتورة</th>
                <th class="p-3">النوع / الحالة</th>
                <th class="p-3">العميل</th>
                <th class="p-3">الكاشير</th>
                <th class="p-3">الإجمالي</th>
                <th class="p-3">المدفوع / الآجل</th>
                <th class="p-3">التاريخ</th>
                <th class="p-3">عرض</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($orders as $o)
            <tr>
                <td class="p-3 font-bold">{{ $o->invoice_number }}</td>
                <td class="p-3">
                    <span class="px-2 py-0.5 rounded {{ $o->type === 'return' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }} font-semibold">
                        {{ $o->type === 'return' ? 'مرتجع' : 'مبيعات' }} ({{ $o->status }})
                    </span>
                </td>
                <td class="p-3">{{ $o->customer?->name ?? 'عميل نقدي' }}</td>
                <td class="p-3">{{ $o->user?->name }}</td>
                <td class="p-3 font-bold">{{ number_format((float) $o->total_amount, $store->decimal_places) }} {{ $sym }}</td>
                <td class="p-3">{{ number_format((float) $o->paid_amount, $store->decimal_places) }} / <span class="text-rose-600">{{ number_format((float) $o->credit_amount, $store->decimal_places) }}</span></td>
                <td class="p-3">{{ $o->sold_at?->format('Y-m-d H:i') }}</td>
                <td class="p-3">
                    <a href="{{ route('orders.show', $o->id) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 font-semibold">التفاصيل / مرتجع</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="p-6 text-center text-slate-400">لا توجد فواتير مسجلة حتى الآن.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $orders->links() }}</div>
</div>
@endsection
