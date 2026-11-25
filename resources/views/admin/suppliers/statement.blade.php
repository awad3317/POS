@extends('layouts.app')
@section('title', 'كشف حساب مورد')
@section('header', 'كشف حساب المورد: ' . $supplier->name)
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 text-xs">
        الرصيد الافتتاحي: <strong>{{ format_money($statement['opening_balance'], $store->decimal_places) }} {{ $sym }}</strong> |
        الرصيد الختامي المستحق: <strong class="text-rose-600">{{ format_money($statement['closing_balance'], $store->decimal_places) }} {{ $sym }}</strong>
    </div>
    <div class="flex gap-2">
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">إرسال إشعار واتساب</a>
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
            @forelse($statement['entries'] as $row)
            <tr>
                <td class="p-3">{{ $row['date'] }}</td>
                <td class="p-3">{{ $row['type'] }}</td>
                <td class="p-3">{{ $row['reference'] }}</td>
                <td class="p-3 font-semibold">{{ format_money($row['signed_amount'], $store->decimal_places) }} {{ $sym }}</td>
                <td class="p-3 font-bold">{{ format_money($row['running_balance'], $store->decimal_places) }} {{ $sym }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد حركات مسجلة في هذه الفترة.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
