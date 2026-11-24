@extends('layouts.app')
@section('title', 'كشف حساب عميل')
@section('header', 'كشف حساب العميل: ' . $customer->name)
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 text-xs">
        الرصيد الافتتاحي: <strong>{{ $customer->opening_balance }} {{ $sym }}</strong> |
        إجمالي الدين الحالي: <strong class="text-rose-600">{{ $customer->current_balance }} {{ $sym }}</strong>
    </div>
    <div class="flex gap-2">
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">تذكير بالرصيد عبر واتساب</a>
        <button onclick="window.print()" class="px-3.5 py-2 rounded-xl border bg-white text-xs font-semibold">طباعة</button>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-xs text-right">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="p-3">التاريخ</th>
                <th class="p-3">العملية</th>
                <th class="p-3">المرجع</th>
                <th class="p-3">القيمة</th>
                <th class="p-3">الرصيد التراكمي</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($events as $ev)
            <tr>
                <td class="p-3">{{ $ev['date'] }}</td>
                <td class="p-3">{{ $ev['type'] }}</td>
                <td class="p-3">{{ $ev['reference'] }}</td>
                <td class="p-3 font-semibold">{{ $ev['signed_amount'] }} {{ $sym }}</td>
                <td class="p-3 font-bold">{{ $ev['running_balance'] }} {{ $sym }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد حركات آجل أو تسديد مسجلة.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
