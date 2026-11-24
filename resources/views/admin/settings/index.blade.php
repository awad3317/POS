@extends('layouts.app')
@section('title', 'الإعدادات والموظفين والأجهزة')
@section('header', 'إعدادات المتجر وسعر الصرف والأجهزة المعتمدة')
@section('content')
@php
    $flags = $store->feature_flags ?? [];
    $receipt = $store->receipt_settings ?? [];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
    <!-- Store Settings -->
    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <h2 class="font-bold text-sm mb-3">الإعدادات العامة وميزات النشاط</h2>
        <form method="POST" action="{{ route('settings.update') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-semibold mb-1">اسم المحل</label>
                    <input type="text" name="name" value="{{ $store->name }}" required class="w-full border rounded-xl px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold mb-1">نوع النشاط</label>
                    <select name="business_type" class="w-full border rounded-xl px-3 py-2">
                        <option value="grocery" @selected($store->business_type === 'grocery')>بقالة / سوبرماركت</option>
                        <option value="clothing" @selected($store->business_type === 'clothing')>ملابس وأحذية</option>
                        <option value="perfumes" @selected($store->business_type === 'perfumes')>عطور</option>
                        <option value="general" @selected($store->business_type === 'general')>تجزئة عامة</option>
                    </select>
                </div>
                <div>
                    <label class="block font-semibold mb-1">العملة الثانوية للصرف</label>
                    <input type="text" name="secondary_currency_code" value="{{ $store->secondary_currency_code }}" placeholder="SAR أو USD" class="w-full border rounded-xl px-3 py-2">
                </div>
                <div>
                    <label class="block font-semibold mb-1">مقاس الطابعة</label>
                    <select name="paper_width" class="w-full border rounded-xl px-3 py-2">
                        <option value="80mm" @selected(($receipt['paper_width'] ?? '80mm') === '80mm')>80mm</option>
                        <option value="58mm" @selected(($receipt['paper_width'] ?? '80mm') === '58mm')>58mm</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-2">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="feature_flags[variants]" value="1" @checked(!empty($flags['variants']))> المقاسات والألوان</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="feature_flags[multi_units]" value="1" @checked(!empty($flags['multi_units']))> تعدد الوحدات</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="feature_flags[scale_plu]" value="1" @checked(!empty($flags['scale_plu']))> باركود الميزان</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="feature_flags[exchange_rate]" value="1" @checked(!empty($flags['exchange_rate']))> تحديث سعر الصرف</label>
            </div>

            <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold">حفظ الإعدادات</button>
        </form>

        <!-- Exchange Rate Tool -->
        <div class="mt-6 pt-4 border-t border-slate-200">
            <h3 class="font-bold text-sm mb-2 text-amber-800">أداة تحديث سعر الصرف الفوري</h3>
            <form method="POST" action="{{ route('settings.exchange-rate') }}" class="flex gap-2">
                @csrf
                <input type="number" step="0.0001" name="exchange_rate" value="{{ $store->exchange_rate }}" required placeholder="سعر الصرف الجديد" class="border rounded-xl px-3 py-2 flex-1">
                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-600 text-white font-bold">تحديث وإعادة التسعير</button>
            </form>
        </div>
    </div>

    <!-- Terminals & Users -->
    <div class="space-y-6">
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-bold text-sm mb-3">أجهزة الكاشير المعتمدة (إيقاف فوري عن بعد)</h2>
            <form method="POST" action="{{ route('settings.terminals.store') }}" class="flex gap-2 mb-3">
                @csrf
                <input type="text" name="code" required placeholder="الرمز (T02)" class="border rounded-xl px-3 py-1.5 w-28">
                <input type="text" name="name" required placeholder="اسم الجهاز" class="border rounded-xl px-3 py-1.5 flex-1">
                <button type="submit" class="px-3 py-1.5 bg-slate-900 text-white rounded-xl font-bold">+ إضافة</button>
            </form>
            <div class="divide-y">
                @foreach($terminals as $term)
                <div class="py-2 flex items-center justify-between">
                    <div>
                        <strong>{{ $term->code }}</strong> — {{ $term->name }}
                        <span class="px-1.5 py-0.5 rounded {{ $term->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $term->is_active ? 'نشط' : 'موقوف' }}
                        </span>
                    </div>
                    @if($term->is_active)
                    <form method="POST" action="{{ route('settings.terminals.revoke', $term->id) }}">
                        @csrf
                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-600 text-white font-semibold">إيقاف وإبطال التوكن</button>
                    </form>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h2 class="font-bold text-sm mb-3">حسابات الدخول والصلاحيات ورمز PIN</h2>
            <form method="POST" action="{{ route('settings.users.store') }}" class="grid grid-cols-2 gap-2 mb-3">
                @csrf
                <input type="text" name="name" required placeholder="الاسم" class="border rounded-xl px-2.5 py-1.5">
                <input type="email" name="email" required placeholder="البريد" class="border rounded-xl px-2.5 py-1.5">
                <input type="password" name="password" required placeholder="كلمة المرور (8+)" class="border rounded-xl px-2.5 py-1.5">
                <input type="text" name="pin" maxlength="4" placeholder="PIN (4 أرقام)" class="border rounded-xl px-2.5 py-1.5">
                <select name="role" class="border rounded-xl px-2.5 py-1.5">
                    <option value="cashier">كاشير (cashier)</option>
                    <option value="storekeeper">أمين مخزن (storekeeper)</option>
                    <option value="manager">مدير فرع (manager)</option>
                    <option value="owner">مالك (owner)</option>
                </select>
                <button type="submit" class="bg-emerald-600 text-white rounded-xl font-bold py-1.5">+ إنشاء حساب</button>
            </form>
            <div class="divide-y">
                @foreach($users as $u)
                <div class="py-1.5 flex justify-between">
                    <span>{{ $u->name }} ({{ $u->email }})</span>
                    <span class="font-semibold text-slate-600">{{ $u->getRoleNames()->implode(', ') }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
