@extends('layouts.app')
@section('title', 'المصروفات التشغيلية')
@section('header', 'المصروفات اليومية والتشغيلية')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <h2 class="font-bold text-sm mb-3">تسجيل مصروف جديد</h2>
        <form method="POST" action="{{ route('expenses.store') }}" class="space-y-3 text-xs">
            @csrf
            <div>
                <label class="block font-semibold mb-1">البند</label>
                <select name="category" class="w-full border rounded-xl px-3 py-2">
                    <option value="كهرباء وطاقة">كهرباء وطاقة</option>
                    <option value="إيجار المحل">إيجار المحل</option>
                    <option value="ماء ونظافة">ماء ونظافة</option>
                    <option value="أكياس وتغليف">أكياس وتغليف</option>
                    <option value="صيانة">صيانة</option>
                    <option value="نثريات">نثريات عامة</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-1">المبلغ *</label>
                <input type="number" step="0.0001" name="amount" required class="w-full border rounded-xl px-3 py-2">
            </div>
            <div>
                <label class="block font-semibold mb-1">مصدر الصرف</label>
                <select name="payment_source" class="w-full border rounded-xl px-3 py-2">
                    <option value="owner_pocket">جيب المالك / الإدارة</option>
                    <option value="cash_drawer">درج الكاشير المفتوح</option>
                    <option value="bank">البنك</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-1">الوردية (عند الصرف من الدرج)</label>
                <select name="shift_id" class="w-full border rounded-xl px-3 py-2">
                    <option value="">— بدون وردية —</option>
                    @foreach($openShifts as $sh)
                        <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                    @endforeach
                </select>
            </div>
            <input type="text" name="notes" placeholder="بيان / ملاحظة" class="w-full border rounded-xl px-3 py-2">
            <button type="submit" class="w-full py-2.5 bg-emerald-600 text-white rounded-xl font-bold">حفظ المصروف</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="p-4 border-b flex justify-between text-xs">
            <span class="font-bold text-sm">سجل المصروفات</span>
            <span>إجمالي هذا الشهر: <strong class="text-rose-600">{{ number_format((float) $monthTotal, $store->decimal_places) }} {{ $sym }}</strong></span>
        </div>
        <table class="w-full text-xs text-right">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="p-2.5">البند</th>
                    <th class="p-2.5">المبلغ</th>
                    <th class="p-2.5">المصدر</th>
                    <th class="p-2.5">التاريخ</th>
                    <th class="p-2.5">ملاحظة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($expenses as $ex)
                <tr>
                    <td class="p-2.5 font-semibold">{{ $ex->category }}</td>
                    <td class="p-2.5 font-bold text-rose-600">{{ number_format((float) $ex->amount, $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-2.5">{{ $ex->payment_source }}</td>
                    <td class="p-2.5">{{ $ex->spent_at?->format('Y-m-d H:i') }}</td>
                    <td class="p-2.5 text-slate-500">{{ $ex->notes }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-400">لا توجد مصروفات مسجلة.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
