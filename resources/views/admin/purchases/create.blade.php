@extends('layouts.app')
@section('title', 'إدخال فاتورة مشتريات')
@section('header', 'فاتورة مشتريات جديدة (تغذية المخزن وتحديث متوسط التكلفة)')
@section('content')
<div class="max-w-4xl bg-white rounded-2xl border border-slate-200 p-6"
     x-data="{
        items: [{ product_id: '{{ $products->first()?->id }}', quantity: 1, unit_cost: 0 }],
        paidAmount: 0,
        discountAmount: 0,
        get subtotal() {
            return this.items.reduce((sum, i) => sum + ((parseFloat(i.quantity) || 0) * (parseFloat(i.unit_cost) || 0)), 0);
        },
        get total() { return Math.max(0, this.subtotal - (parseFloat(this.discountAmount) || 0)); },
        get credit() { return Math.max(0, this.total - (parseFloat(this.paidAmount) || 0)); }
     }">

    <form method="POST" action="{{ route('purchases.store') }}" class="space-y-4 text-xs">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label class="block font-semibold mb-1">المورد</label>
                <select name="supplier_id" class="w-full border rounded-xl px-3 py-2">
                    <option value="">— شراء نقدي بدون مورد —</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-1">رقم فاتورة المورد</label>
                <input type="text" name="reference_number" class="w-full border rounded-xl px-3 py-2" placeholder="اختياري">
            </div>
            <div>
                <label class="block font-semibold mb-1">مصدر الدفع</label>
                <select name="payment_source" class="w-full border rounded-xl px-3 py-2">
                    <option value="owner_pocket">جيب المالك / الإدارة</option>
                    <option value="cash_drawer">درج الكاشير</option>
                    <option value="bank">البنك / حوالة</option>
                </select>
            </div>
        </div>

        <div class="space-y-2 border-t pt-3">
            <div class="flex justify-between items-center">
                <span class="font-bold">الأصناف المشتراة</span>
                <button type="button" @click="items.push({ product_id: '{{ $products->first()?->id }}', quantity: 1, unit_cost: 0 })"
                        class="px-3 py-1 rounded-lg bg-slate-800 text-white">+ سطر جديد</button>
            </div>

            <template x-for="(row, idx) in items" :key="idx">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 bg-slate-50 p-2.5 rounded-xl">
                    <select :name="`items[${idx}][product_id]`" x-model="row.product_id" class="sm:col-span-2 border bg-white rounded-lg px-2 py-1.5">
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->barcode }})</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.001" :name="`items[${idx}][quantity]`" x-model="row.quantity" placeholder="الكمية" class="border bg-white rounded-lg px-2 py-1.5">
                    <input type="number" step="0.0001" :name="`items[${idx}][unit_cost]`" x-model="row.unit_cost" placeholder="سعر تكلفة الوحدة" class="border bg-white rounded-lg px-2 py-1.5">
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl">
            <div>
                <label class="block font-semibold mb-1">خصم الفاتورة</label>
                <input type="number" step="0.0001" name="discount_amount" x-model="discountAmount" class="w-full border bg-white rounded-lg px-3 py-1.5">
            </div>
            <div>
                <label class="block font-semibold mb-1">المدفوع الآن</label>
                <input type="number" step="0.0001" name="paid_amount" x-model="paidAmount" class="w-full border bg-white rounded-lg px-3 py-1.5">
            </div>
            <div>
                <label class="block font-semibold mb-1">المتبقي آجل للمورد</label>
                <input type="number" step="0.0001" name="credit_amount" :value="credit.toFixed(4)" readonly class="w-full border bg-slate-100 rounded-lg px-3 py-1.5 font-bold text-rose-600">
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('purchases.index') }}" class="px-4 py-2 border rounded-xl">إلغاء</a>
            <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-xl font-bold">اعتماد فاتورة الشراء</button>
        </div>
    </form>
</div>
@endsection
