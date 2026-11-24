@extends('layouts.app')
@section('title', 'حسابات الموردين')
@section('header', 'حسابات الموردين وسندات الصرف')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4" x-data="{ addModal: false, paySupplier: null }">
    <div class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 text-xs">
        إجمالي المستحق للموردين على المحل: <span class="font-bold text-rose-600 text-sm">{{ number_format((float) $totalPayables, $store->decimal_places) }} {{ $sym }}</span>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('purchases.create') }}" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold">+ فاتورة مشتريات</a>
        <button @click="addModal = true" class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">+ مورد جديد</button>
    </div>

    <div class="w-full bg-white rounded-2xl border border-slate-200 overflow-hidden mt-2">
        <table class="w-full text-xs text-right">
            <thead class="bg-slate-50 text-slate-600 border-b">
                <tr>
                    <th class="p-3">المورد / الشركة</th>
                    <th class="p-3">الهاتف</th>
                    <th class="p-3">إجمالي المشتريات</th>
                    <th class="p-3">إجمالي المسدد</th>
                    <th class="p-3">الرصيد المتبقي له</th>
                    <th class="p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($suppliers as $s)
                <tr>
                    <td class="p-3 font-semibold">{{ $s->name }} <span class="text-slate-400">{{ $s->company_name }}</span></td>
                    <td class="p-3">{{ $s->phone ?? '—' }}</td>
                    <td class="p-3">{{ number_format((float) ($s->total_purchases ?? 0), $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-3 text-emerald-700">{{ number_format((float) ($s->total_payments ?? 0), $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-3 font-bold text-rose-600">{{ number_format((float) $s->current_balance, $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-3 flex items-center gap-2">
                        <button type="button" @click="paySupplier = { id: {{ $s->id }}, name: '{{ addslashes($s->name) }}' }"
                                class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-semibold">سند صرف</button>
                        <a href="{{ route('suppliers.statement', $s->id) }}" class="text-slate-700 underline">كشف حساب</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="p-6 text-center text-slate-400">لا يوجد موردون مسجلون.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add Supplier Modal -->
    <div x-show="addModal" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <form method="POST" action="{{ route('suppliers.store') }}" @click.outside="addModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-3 text-xs">
            @csrf
            <h3 class="font-bold text-sm">إضافة مورد جديد</h3>
            <input type="text" name="name" required placeholder="اسم المورد *" class="w-full border rounded-xl px-3 py-2">
            <input type="text" name="company_name" placeholder="اسم الشركة / الوكالة" class="w-full border rounded-xl px-3 py-2">
            <input type="text" name="phone" placeholder="رقم الهاتف / واتساب" class="w-full border rounded-xl px-3 py-2">
            <input type="number" step="0.0001" name="opening_balance" value="0" placeholder="رصيد افتتاحي مستحق للمورد" class="w-full border rounded-xl px-3 py-2">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="addModal = false" class="px-3 py-1.5 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">حفظ</button>
            </div>
        </form>
    </div>

    <!-- Pay Supplier Modal -->
    <div x-show="paySupplier" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <form method="POST" :action="`/suppliers/${paySupplier?.id}/payments`" @click.outside="paySupplier = null" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-3 text-xs">
            @csrf
            <h3 class="font-bold text-sm">سند صرف للمورد: <span x-text="paySupplier?.name"></span></h3>
            <input type="number" step="0.0001" name="amount" required placeholder="المبلغ المسدد *" class="w-full border rounded-xl px-3 py-2">
            <select name="method" class="w-full border rounded-xl px-3 py-2">
                <option value="cash">نقدي</option>
                <option value="kuraimi">الكريمي</option>
                <option value="jawali">جوالي</option>
                <option value="floosak">فلوسك</option>
                <option value="onecash">ون كاش</option>
                <option value="bank_transfer">تحويل بنكي</option>
            </select>
            <select name="payment_source" class="w-full border rounded-xl px-3 py-2">
                <option value="owner_pocket">من حساب المالك / الإدارة</option>
                <option value="cash_drawer">من درج الكاشير المفتوح</option>
                <option value="bank">من البنك</option>
            </select>
            <select name="shift_id" class="w-full border rounded-xl px-3 py-2">
                <option value="">— الوردية (مطلوب فقط عند الصرف من الدرج) —</option>
                @foreach($openShifts as $sh)
                    <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                @endforeach
            </select>
            <input type="text" name="reference_number" placeholder="رقم الحوالة / الإشعار (اختياري)" class="w-full border rounded-xl px-3 py-2">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="paySupplier = null" class="px-3 py-1.5 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">اعتماد الصرف</button>
            </div>
        </form>
    </div>
</div>
@endsection
