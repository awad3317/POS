@extends('layouts.app')
@section('title', 'كرت الصنف')
@section('header', 'كرت حركة الصنف: ' . $product->name)
@section('content')
<div class="mb-4 bg-white rounded-xl border border-slate-200 px-4 py-3 text-xs flex justify-between">
    <span>الباركود: <strong>{{ $product->barcode ?? '—' }}</strong></span>
    <span>الرصيد الحالي المعتمد: <strong class="text-emerald-700 text-sm">{{ $product->quantity }}</strong></span>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-xs text-right">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="p-3">التاريخ</th>
                <th class="p-3">نوع الحركة</th>
                <th class="p-3">قبل</th>
                <th class="p-3">التغير</th>
                <th class="p-3">بعد</th>
                <th class="p-3">ملاحظة</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach($movements as $mv)
            <tr>
                <td class="p-3">{{ $mv->created_at?->format('Y-m-d H:i') }}</td>
                <td class="p-3">{{ $mv->type }} @if($mv->is_negative_alert) <span class="text-rose-600 font-bold">(تنبيه سالب)</span> @endif</td>
                <td class="p-3">{{ $mv->balance_before }}</td>
                <td class="p-3 font-bold">{{ $mv->quantity_delta }}</td>
                <td class="p-3 font-bold">{{ $mv->balance_after }}</td>
                <td class="p-3 text-slate-500">{{ $mv->note }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $movements->links() }}</div>
</div>
@endsection
