@extends('layouts.app')
@section('title', 'جلسات البيع والصندوق')
@section('header', 'إدارة جلسات البيع وتقارير الصندوق ')
@section('breadcrumb', 'الرئيسية / جلسات البيع والصندوق')

@section('content')
@php $sym =$store->currency_symbol; @endphp

<div class="space-y-6">

    {{-- فتح جلسة بيع جديدة --}}
    <section class="rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-sm font-bold text-gray-900 sm:text-base">فتح جلسة بيع جديدة</h2>
            <p class="mt-0.5 text-xs text-gray-500">اختر جهاز الكاشير وأدخل عهدة بداية الوردية</p>
        </div>

        <form method="POST" action="{{ route('shifts.open') }}" class="p-5 sm:p-6">
            @csrf
            <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-12">
                <div class="sm:col-span-5">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">جهاز الكاشير</label>
                    <select name="terminal_id" required
                            class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-xs font-medium text-gray-800 focus:border-emerald-500 focus:outline-none">
                        @foreach ($terminals as $t)
                            <option value="{{ $t->id }}">{{ $t->name }} ({{$t->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-4">
                    <label class="mb-1.5 block text-xs font-semibold text-gray-700">عهدة بداية جلسة البيع ({{ $sym }})</label>
                    <input type="number" step="any" name="opening_cash" value="0" required
                           class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3.5 text-xs font-semibold text-gray-900 tabular-nums focus:border-emerald-500 focus:outline-none">
                </div>

                <div class="sm:col-span-3">
                    <button type="submit"
                            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>فتح جلسة بيع جديدة</span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    {{-- سجل جلسات البيع--}}
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 class="text-sm font-bold text-gray-900 sm:text-base">سجل جلسات البيع وجرد الصناديق</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs sm:text-[13px]">
                <thead class="border-b border-gray-100 bg-gray-50/80 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="whitespace-nowrap px-5 py-3.5">#</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الجهاز / الكاشير</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الحالة</th>
                        <th class="whitespace-nowrap px-5 py-3.5">النقد المتوقع</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الجرد الفعلي</th>
                        <th class="whitespace-nowrap px-5 py-3.5">الفارق</th>
                        <th class="whitespace-nowrap px-5 py-3.5 text-left"> تقرير جلسات البيع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($shifts as $sh)
                        <tr class="transition hover:bg-gray-50/70">
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs font-bold text-gray-900">
                                #{{ $sh->id }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <div class="font-semibold text-gray-900">{{ $sh->terminal?->name }}</div>
                                <div class="text-[11px] text-gray-500">{{ $sh->user?->name }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $sh->status === 'open' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-gray-100 text-gray-700' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $sh->status === 'open' ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                    {{ $sh->status === 'open' ? 'مفتوحة' : 'مغلقة' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-semibold text-gray-900 tabular-nums">
                                {{ format_money($sh->expected_cash, $store->decimal_places) }} {{$sym }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-gray-700 tabular-nums">
                                {{ $sh->actual_cash !== null ? format_money($sh->actual_cash, $store->decimal_places) . ' ' .$sym : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-bold tabular-nums {{ $sh->difference !== null && \App\Support\MoneyMath::isNegative($sh->difference) ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ $sh->difference !== null ? format_money($sh->difference, $store->decimal_places) . ' ' .$sym : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-left">
                                <a href="{{ route('shifts.show', $sh->id) }}"
                                   class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700">
                                    <span>عرض التقرير</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-xs text-gray-400">
                                لا توجد ورديات مسجلة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 bg-gray-50/40 px-5 py-3">
            {{ $shifts->links() }}
        </div>
    </section>

</div>
@endsection