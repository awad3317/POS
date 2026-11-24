@extends('layouts.app')
@section('title', 'كشف حساب العامل')
@section('header', 'كشف حساب العامل: ' . $employee->name)
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 text-xs">
        الرصيد الافتتاحي: <strong>{{ $statement['opening_balance'] }} {{ $sym }}</strong> |
        صافي الرصيد الحالي: <strong class="{{ (float)$statement['closing_balance'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $statement['closing_balance'] }} {{ $sym }}</strong>
    </div>
    <div class="flex gap-2">
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">إرسال كشف الراتب عبر واتساب</a>
        <button onclick="window.print()" class="px-3.5 py-2 rounded-xl border bg-white text-xs font-semibold">طباعة</button>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-xs text-right">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="p-3">التاريخ</th>
                <th class="p-3">النوع</th>
                <th class="p-3">المصدر</th>
                <th class="p-3">القيمة</th>
                <th class="p-3">الرصيد التراكمي</th>
                <th class="p-3">ملاحظات</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($statement['entries'] as $row)
            <tr>
                <td class="p-3">{{ $row['date'] }}</td>
                <td class="p-3">{{ $row['type'] }}</td>
                <td class="p-3">{{ $row['payment_source'] ?? 'قيد محاسبي' }}</td>
                <td class="p-3 font-semibold {{ (float)$row['signed_amount'] < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ $row['signed_amount'] }} {{ $sym }}</td>
                <td class="p-3 font-bold">{{ $row['running_balance'] }} {{ $sym }}</td>
                <td class="p-3 text-slate-500">{{ $row['notes'] }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="p-6 text-center text-slate-400">لا توجد حركات مسجلة لهذا العامل.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
