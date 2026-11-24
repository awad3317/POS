@extends('layouts.app')
@section('title', 'شاشة الكاشير')
@section('header', 'شاشة الكاشير الأوفلاين (POS)')
@section('content')
<div class="max-w-xl mx-auto bg-white rounded-2xl border border-slate-200 p-8 text-center">
    <div class="text-4xl mb-3">⚡</div>
    <h2 class="text-xl font-bold text-slate-900 mb-2">نقطة البيع السريعة — {{ auth()->user()->store->name }}</h2>
    <p class="text-sm text-slate-600 mb-4">تم تفعيل حساب الكاشير بنجاح. سيتم ربط محرك Dexie.js الأوفلاين الكامل في المرحلة الرابعة.</p>
    <a href="{{ route('dashboard') }}" class="inline-block px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold">العودة للرئيسية</a>
</div>
@endsection
