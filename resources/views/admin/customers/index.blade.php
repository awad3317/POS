@extends('layouts.app')
@section('title', 'العملاء ودفتر الديون')
@section('header', 'العملاء ودفتر الديون وسندات القبض')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4" x-data="{ addCust: false, settleCust: null }">
    <div class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 text-xs">
        إجمالي الديون المستحقة عند العملاء: <span class="font-bold text-emerald-700 text-sm">{{ number_format((float) $totalReceivables, $store->decimal_places) }} {{ $sym }}</span>
    </div>

    <div class="flex gap-2">
        <form method="GET" action="{{ route('customers.index') }}" class="flex gap-1">
            <input type="text" name="q" value="{{ $search }}" placeholder="بحث بالاسم أو الهاتف..." class="border bg-white rounded-xl px-3 py-1.5 text-xs">
            <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white rounded-xl text-xs">بحث</button>
        </form>
        <button @click="addCust = true" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">+ عميل جديد</button>
    </div>

    <div class="w-full bg-white rounded-2xl border border-slate-200 overflow-hidden mt-2">
        <table class="w-full text-xs text-right">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="p-3">العميل</th>
                    <th class="p-3">الهاتف</th>
                    <th class="p-3">السقف الائتماني</th>
                    <th class="p-3">الرصيد المستحق عليه</th>
                    <th class="p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $c)
                <tr>
                    <td class="p-3 font-semibold">{{ $c->name }}</td>
                    <td class="p-3">{{ $c->phone ?? '—' }}</td>
                    <td class="p-3">{{ number_format((float) $c->credit_limit, $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-3 font-bold {{ (float)$c->current_balance > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ number_format((float) $c->current_balance, $store->decimal_places) }} {{ $sym }}
                    </td>
                    <td class="p-3 flex items-center gap-2">
                        <button type="button" @click="settleCust = { id: {{ $c->id }}, name: '{{ addslashes($c->name) }}' }"
                                class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-semibold">سند قبض</button>
                        <a href="{{ route('customers.statement', $c->id) }}" class="underline text-slate-700">كشف حساب</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-400">لا يوجد عملاء مسجلون.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3 border-t">{{ $customers->links() }}</div>
    </div>

    <!-- Add Customer Modal -->
    <div x-show="addCust" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <form method="POST" action="{{ route('customers.store') }}" @click.outside="addCust = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-3 text-xs">
            @csrf
            <h3 class="font-bold text-sm">إضافة عميل جديد</h3>
            <input type="text" name="name" required placeholder="اسم العميل *" class="w-full border rounded-xl px-3 py-2">
            <input type="text" name="phone" placeholder="رقم الهاتف / واتساب" class="w-full border rounded-xl px-3 py-2">
            <input type="number" step="0.0001" name="credit_limit" value="50000" placeholder="السقف الائتماني المسموح" class="w-full border rounded-xl px-3 py-2">
            <input type="number" step="0.0001" name="opening_balance" value="0" placeholder="رصيد دين افتتاحي سابق" class="w-full border rounded-xl px-3 py-2">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="addCust = false" class="px-3 py-1.5 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">حفظ العميل</button>
            </div>
        </form>
    </div>

    <!-- Quick Credit Settlement Modal -->
    <div x-show="settleCust" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <form method="POST" :action="`/customers/${settleCust?.id}/settlements`" @click.outside="settleCust = null" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-3 text-xs">
            @csrf
            <h3 class="font-bold text-sm">سند قبض تسديد دين: <span x-text="settleCust?.name"></span></h3>
            <input type="number" step="0.0001" name="amount" required placeholder="المبلغ المستلم *" class="w-full border rounded-xl px-3 py-2">
            <select name="method" class="w-full border rounded-xl px-3 py-2">
                <option value="cash">نقدي (يدخل درج الوردية)</option>
                <option value="kuraimi">الكريمي</option>
                <option value="jawali">جوالي</option>
                <option value="floosak">فلوسك</option>
                <option value="onecash">ون كاش</option>
            </select>
            <select name="shift_id" class="w-full border rounded-xl px-3 py-2">
                <option value="">— الوردية الحالية (للنقدي) —</option>
                @foreach($openShifts as $sh)
                    <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                @endforeach
            </select>
            <input type="text" name="reference_number" placeholder="رقم الإشعار / الحوالة" class="w-full border rounded-xl px-3 py-2">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="settleCust = null" class="px-3 py-1.5 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">تأكيد القبض</button>
            </div>
        </form>
    </div>
</div>
@endsection
