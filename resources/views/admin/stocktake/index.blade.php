@extends('layouts.app')
@section('title', 'الجرد والتسويات المخزنية')
@section('header', 'جرد المخزون السريع وتسجيل التوالف')
@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <h2 class="font-bold text-sm mb-3">تسجيل جرد أو تسوية مخزنية</h2>
        <form method="POST" action="{{ route('stocktake.store') }}" class="space-y-3 text-xs">
            @csrf
            <div>
                <label class="block font-semibold mb-1">الصنف *</label>
                <select name="product_id" required class="w-full border rounded-xl px-3 py-2">
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} (الحالي: {{ $p->quantity }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-1">نوع العملية</label>
                <select name="mode" class="w-full border rounded-xl px-3 py-2">
                    <option value="stocktake">مطابقة الجرد الفعلي (الكمية الفعلية على الرف)</option>
                    <option value="adjustment_out">إخراج تالف / هالك (-)</option>
                    <option value="adjustment_in">إضافة تسوية مخزنية (+)</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold mb-1">الكمية *</label>
                <input type="number" step="0.001" name="quantity" required class="w-full border rounded-xl px-3 py-2">
            </div>
            <div>
                <label class="block font-semibold mb-1">سبب التسوية / ملاحظة</label>
                <input type="text" name="note" class="w-full border rounded-xl px-3 py-2" placeholder="جرد دوري / تالف">
            </div>
            <button type="submit" class="w-full py-2.5 bg-emerald-600 text-white rounded-xl font-bold">حفظ قيد المخزون</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="p-4 border-b font-bold text-sm">آخر 30 حركة في دفتر المخزون</div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="p-2.5">الصنف</th>
                        <th class="p-2.5">النوع</th>
                        <th class="p-2.5">التغير</th>
                        <th class="p-2.5">الرصيد بعد</th>
                        <th class="p-2.5">الموظف</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($recentMovements as $m)
                    <tr>
                        <td class="p-2.5 font-semibold"><a href="{{ route('products.stock-card', $m->product_id) }}" class="underline">{{ $m->product?->name }}</a></td>
                        <td class="p-2.5">{{ $m->type }}</td>
                        <td class="p-2.5 font-bold {{ (float)$m->quantity_delta < 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $m->quantity_delta }}</td>
                        <td class="p-2.5 font-bold">{{ $m->balance_after }}</td>
                        <td class="p-2.5 text-slate-500">{{ $m->user?->name }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
