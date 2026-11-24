@extends('layouts.app')
@section('title', 'الورديات والصندوق')
@section('header', 'إدارة الورديات وتقارير الصندوق (Z-Report)')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="bg-white rounded-2xl border border-slate-200 p-4 mb-4">
    <form method="POST" action="{{ route('shifts.open') }}" class="flex flex-wrap items-end gap-3 text-xs">
        @csrf
        <div>
            <label class="block font-semibold mb-1">جهاز الكاشير</label>
            <select name="terminal_id" required class="border rounded-xl px-3 py-2">
                @foreach($terminals as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block font-semibold mb-1">عهدة بداية الوردية ({{ $sym }})</label>
            <input type="number" step="0.0001" name="opening_cash" value="0" required class="border rounded-xl px-3 py-2">
        </div>
        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold">فتح وردية جديدة</button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
    <table class="w-full text-xs text-right">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="p-3">#</th>
                <th class="p-3">الجهاز / الكاشير</th>
                <th class="p-3">الحالة</th>
                <th class="p-3">النقد المتوقع</th>
                <th class="p-3">الجرد الفعلي</th>
                <th class="p-3">الفارق</th>
                <th class="p-3">تفاصيل Z-Report</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($shifts as $sh)
            <tr>
                <td class="p-3 font-bold">#{{ $sh->id }}</td>
                <td class="p-3">{{ $sh->terminal?->name }} — {{ $sh->user?->name }}</td>
                <td class="p-3">
                    <span class="px-2 py-0.5 rounded {{ $sh->status === 'open' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' }} font-semibold">
                        {{ $sh->status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                    </span>
                </td>
                <td class="p-3 font-semibold">{{ number_format((float) $sh->expected_cash, $store->decimal_places) }} {{ $sym }}</td>
                <td class="p-3">{{ $sh->actual_cash !== null ? number_format((float) $sh->actual_cash, $store->decimal_places) . ' ' . $sym : '—' }}</td>
                <td class="p-3 font-bold {{ (float)($sh->difference ?? 0) < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                    {{ $sh->difference !== null ? number_format((float) $sh->difference, $store->decimal_places) . ' ' . $sym : '—' }}
                </td>
                <td class="p-3"><a href="{{ route('shifts.show', $sh->id) }}" class="text-emerald-600 font-bold">عرض التقرير</a></td>
            </tr>
            @empty
            <tr><td colspan="7" class="p-6 text-center text-slate-400">لا توجد ورديات مسجلة.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3 border-t">{{ $shifts->links() }}</div>
</div>
@endsection
