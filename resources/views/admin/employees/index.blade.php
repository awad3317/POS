@extends('layouts.app')
@section('title', 'العمال والرواتب والسلف')
@section('header', 'حسابات العمال والموظفين والرواتب والسلف')
@section('content')
@php $sym = $store->currency_symbol; @endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-4" x-data="{ addEmp: false, txEmp: null }">
    <form method="POST" action="{{ route('employees.accrue-salaries') }}" class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs">
        @csrf
        <span class="font-semibold">اعتماد رواتب الفترة:</span>
        <input type="text" name="period_label" value="{{ now()->format('Y-m') }}" required class="border rounded-lg px-2.5 py-1 w-28 text-center">
        <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold">توليد استحقاق الرواتب</button>
    </form>

    <button @click="addEmp = true" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">+ إضافة عامل / موظف</button>

    <div class="w-full bg-white rounded-2xl border border-slate-200 overflow-hidden mt-2">
        <table class="w-full text-xs text-right">
            <thead class="bg-slate-50 border-b">
                <tr>
                    <th class="p-3">العامل</th>
                    <th class="p-3">المسمى / الراتب</th>
                    <th class="p-3">سلف هذا الشهر</th>
                    <th class="p-3">صافي الرصيد الحالي</th>
                    <th class="p-3">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($employees as $emp)
                <tr>
                    <td class="p-3 font-semibold">
                        {{ $emp->name }}
                        @if(!$emp->user_id)
                            <span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 text-[10px]">بدون دخول نظام</span>
                        @endif
                    </td>
                    <td class="p-3">{{ $emp->job_title ?? 'عامل' }} ({{ format_money($emp->base_salary, $store->decimal_places) }} {{ $sym }} / {{ $emp->salary_type }})</td>
                    <td class="p-3 text-amber-700 font-semibold">{{ format_money($emp->month_advances ?? 0, $store->decimal_places) }} {{ $sym }}</td>
                    <td class="p-3 font-bold {{ \App\Support\MoneyMath::isNegative($emp->current_balance) ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ format_money($emp->current_balance, $store->decimal_places) }} {{ $sym }}
                    </td>
                    <td class="p-3 flex items-center gap-2">
                        <button type="button" @click="txEmp = { id: {{ $emp->id }}, name: '{{ addslashes($emp->name) }}' }"
                                class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 font-semibold">سلفة / حركة</button>
                        <a href="{{ route('employees.statement', $emp->id) }}" class="underline text-slate-700">كشف حساب</a>
                        <a href="{{ \App\Support\WhatsAppLink::forEmployeeStatement($emp, $store) }}" target="_blank" rel="noopener noreferrer"
                           class="px-2 py-1 rounded-lg bg-emerald-600 text-white font-semibold">واتساب</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-400">لا يوجد عمال مسجلون.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Add Employee Modal -->
    <div x-show="addEmp" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <form method="POST" action="{{ route('employees.store') }}" @click.outside="addEmp = false" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-3 text-xs">
            @csrf
            <h3 class="font-bold text-sm">إضافة عامل جديد</h3>
            <input type="text" name="name" required placeholder="اسم العامل *" class="w-full border rounded-xl px-3 py-2">
            <input type="text" name="job_title" placeholder="المسمى الوظيفي (عامل مخزن / كاشير / توصيل)" class="w-full border rounded-xl px-3 py-2">
            <input type="text" name="phone" placeholder="رقم الجوال / واتساب" class="w-full border rounded-xl px-3 py-2">
            <select name="user_id" class="w-full border rounded-xl px-3 py-2">
                <option value="">— بدون حساب دخول للنظام —</option>
                @foreach($storeUsers as $su)
                    <option value="{{ $su->id }}">{{ $su->name }} ({{ $su->email }})</option>
                @endforeach
            </select>
            <div class="grid grid-cols-2 gap-2">
                <select name="salary_type" class="border rounded-xl px-3 py-2">
                    <option value="monthly">راتب شهري</option>
                    <option value="weekly">راتب أسبوعي</option>
                    <option value="daily">أجر يومي</option>
                    <option value="commission_only">عمولة فقط</option>
                </select>
                <input type="number" step="any" name="base_salary" value="0" required placeholder="الراتب الأساسي" class="border rounded-xl px-3 py-2">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="addEmp = false" class="px-3 py-1.5 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">حفظ العامل</button>
            </div>
        </form>
    </div>

    <!-- Quick Advance / Transaction Modal -->
    <div x-show="txEmp" style="display:none;" class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
        <form method="POST" :action="`/employees/${txEmp?.id}/transactions`" @click.outside="txEmp = null" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-3 text-xs">
            @csrf
            <h3 class="font-bold text-sm">حركة مالية للعامل: <span x-text="txEmp?.name"></span></h3>
            <select name="type" class="w-full border rounded-xl px-3 py-2">
                <option value="advance">سلفة / مصروف يومي (-)</option>
                <option value="salary_payout">صرف صافي راتب (-)</option>
                <option value="bonus">حافز / مكافأة (+)</option>
                <option value="deduction">خصم غياب / إتلاف (-)</option>
            </select>
            <input type="number" step="any" name="amount" required placeholder="المبلغ *" class="w-full border rounded-xl px-3 py-2">
            <select name="payment_source" class="w-full border rounded-xl px-3 py-2">
                <option value="owner_pocket">من جيب المالك / الإدارة</option>
                <option value="cash_drawer">من درج الكاشير المفتوح</option>
                <option value="kuraimi">حوالة الكريمي</option>
                <option value="jawali">جوالي</option>
            </select>
            <select name="shift_id" class="w-full border rounded-xl px-3 py-2">
                <option value="">— اختر الوردية (عند السحب من الدرج) —</option>
                @foreach($openShifts as $sh)
                    <option value="{{ $sh->id }}">{{ $sh->terminal?->name }} (#{{ $sh->id }})</option>
                @endforeach
            </select>
            <input type="text" name="notes" placeholder="ملاحظة / سبب السلفة" class="w-full border rounded-xl px-3 py-2">
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="txEmp = null" class="px-3 py-1.5 border rounded-lg">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg font-bold">اعتماد الحركة</button>
            </div>
        </form>
    </div>
</div>
@endsection
