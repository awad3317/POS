@extends('layouts.app')
@section('title', 'فواتير المشتريات')
@section('header', 'فواتير المشتريات ومرتجعات الشراء')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex justify-end mb-4">
    <a href="{{ route('purchases.create') }}" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">+ فاتورة مشتريات جديدة</a>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-xs text-right">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="p-3">المرجع</th>
                <th class="p-3">النوع</th>
                <th class="p-3">المورد</th>
                <th class="p-3">الإجمالي</th>
                <th class="p-3">المدفوع</th>
                <th class="p-3">الآجل</th>
                <th class="p-3">التاريخ</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($purchases as $p)
            <tr>
                <td class="p-3 font-semibold">{{ $p->reference_number }}</td>
                <td class="p-3">{{ $p->type === 'purchase' ? 'فاتورة شراء' : 'مرتجع شراء' }}</td>
                <td class="p-3">{{ $p->supplier?->name ?? 'نقدي مباشر' }}</td>
                <td class="p-3 font-bold">{{ number_format((float) $p->total_amount, $store->decimal_places) }} {{ $sym }}</td>
                <td class="p-3 text-emerald-700">{{ number_format((float) $p->paid_amount, $store->decimal_places) }} {{ $sym }}</td>
                <td class="p-3 text-rose-600">{{ number_format((float) $p->credit_amount, $store->decimal_places) }} {{ $sym }}</td>
                <td class="p-3">{{ $p->purchased_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="p-6 text-center text-slate-400">لا توجد فواتير مشتريات بعد.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $purchases->links() }}</div>
</div>
@endsection
