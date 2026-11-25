@extends('layouts.super-admin')
@section('title', 'إدارة المتاجر والاشتراكات')

@section('content')
@php
    $creds = session('created_credentials');
@endphp

<div x-data="{
        createModal: {{ $errors->hasAny(['store_name', 'owner_name', 'owner_email', 'owner_password', 'owner_pin']) ? 'true' : 'false' }},
        extendTarget: null,
        resetTarget: null,
        copiedToast: false,
        copyCredentials(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copiedToast = true;
                    setTimeout(() => { this.copiedToast = false; }, 2500);
                });
            }
        }
    }" class="space-y-6">

    <!-- Flashed Arabic Credentials Card -->
    @if($creds)
    <div class="bg-emerald-950 text-white rounded-2xl border border-emerald-700 p-5 shadow-md space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[11px] font-bold">بطاقة بيانات الدخول الجاهزة للإرسال</span>
                <h2 class="text-base font-bold mt-1">بيانات متجر: {{ $creds['store_name'] }} — المالك: {{ $creds['owner_name'] }}</h2>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <button type="button"
                        @click="copyCredentials(@js($creds['copy_text']))"
                        class="px-3.5 py-2 rounded-xl bg-white text-slate-900 hover:bg-slate-100 font-bold transition">
                    <span x-show="!copiedToast">📋 نسخ البطاقة</span>
                    <span x-show="copiedToast" class="text-emerald-700">✓ تم النسخ!</span>
                </button>
                <a href="{{ $creds['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"
                   class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-bold transition">
                    📱 إرسال عبر واتساب
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs bg-emerald-900/50 rounded-xl p-3.5 border border-emerald-800">
            <div>
                <div class="text-emerald-300">رابط الدخول</div>
                <div class="font-mono font-bold mt-0.5 truncate" dir="ltr">{{ $creds['login_url'] }}</div>
            </div>
            <div>
                <div class="text-emerald-300">البريد الإلكتروني</div>
                <div class="font-mono font-bold mt-0.5" dir="ltr">{{ $creds['email'] }}</div>
            </div>
            <div>
                <div class="text-emerald-300">كلمة المرور</div>
                <div class="font-mono font-bold mt-0.5" dir="ltr">{{ $creds['password'] }}</div>
            </div>
            @if(!empty($creds['pin']))
            <div>
                <div class="text-emerald-300">رمز PIN للمالك</div>
                <div class="font-mono font-bold mt-0.5" dir="ltr">{{ $creds['pin'] }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- 1. Platform-Wide KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 text-xs">
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs">
            <div class="text-slate-500 font-semibold">إجمالي المتاجر</div>
            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ format_qty($kpis['total_stores']) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-emerald-200 p-4 shadow-2xs">
            <div class="text-emerald-700 font-semibold">المتاجر النشطة</div>
            <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ format_qty($kpis['active_stores']) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-amber-200 p-4 shadow-2xs">
            <div class="text-amber-700 font-semibold">الفترة التجريبية</div>
            <div class="text-2xl font-extrabold text-amber-700 mt-1">{{ format_qty($kpis['trial_stores']) }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-rose-200 p-4 shadow-2xs">
            <div class="text-rose-700 font-semibold">موقوفة / منتهية</div>
            <div class="text-2xl font-extrabold text-rose-700 mt-1">{{ format_qty($kpis['suspended_stores']) }}</div>
        </div>
        <div class="bg-slate-900 text-white rounded-2xl p-4 col-span-2 sm:col-span-1">
            <div class="text-slate-300 font-semibold">فواتير اليوم عبر المنصة</div>
            <div class="text-2xl font-extrabold text-emerald-400 mt-1">{{ format_qty($kpis['today_orders']) }}</div>
        </div>
    </div>

    <!-- 2. Search, Filter & Create Store Bar -->
    <div class="bg-white rounded-2xl border border-slate-200 p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
        <form method="GET" action="{{ route('super-admin.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="text" name="q" value="{{ $search }}" placeholder="ابحث باسم المتجر، المالك، البريد، أو الجوال..."
                   class="border border-slate-300 rounded-xl px-3.5 py-2 w-64 focus:border-emerald-600 focus:outline-none">

            <select name="business_type" class="border border-slate-300 rounded-xl px-3 py-2 bg-white">
                <option value="">كل الأنشطة التجارية</option>
                <option value="grocery" @selected($businessType === 'grocery')>بقالة / سوبرماركت</option>
                <option value="clothing" @selected($businessType === 'clothing')>ملابس وأحذية</option>
                <option value="perfumes" @selected($businessType === 'perfumes')>عطور وتجميل</option>
                <option value="general" @selected($businessType === 'general')>تجزئة عامة</option>
            </select>

            <select name="subscription_status" class="border border-slate-300 rounded-xl px-3 py-2 bg-white">
                <option value="">كل حالات الاشتراك</option>
                <option value="active" @selected($subscriptionStatus === 'active')>نشط (Active)</option>
                <option value="trial" @selected($subscriptionStatus === 'trial')>تجريبي (Trial)</option>
                <option value="suspended" @selected($subscriptionStatus === 'suspended')>موقوف (Suspended)</option>
                <option value="expired" @selected($subscriptionStatus === 'expired')>منتهي الصلاحية</option>
            </select>

            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold">
                تصفية
            </button>
            @if($search !== '' || $businessType !== '' || $subscriptionStatus !== '')
                <a href="{{ route('super-admin.index') }}" class="px-3 py-2 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-50">
                    إعادة ضبط
                </a>
            @endif
        </form>

        <button type="button" @click="createModal = true"
                class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-xs transition">
            + إنشاء متجر ومالك جديد
        </button>
    </div>

    <!-- 3. Stores Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-right">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">المتجر</th>
                        <th class="p-3.5">نوع النشاط</th>
                        <th class="p-3.5">بيانات المالك</th>
                        <th class="p-3.5">الأجهزة / الفواتير</th>
                        <th class="p-3.5">حالة الاشتراك</th>
                        <th class="p-3.5">ينتهي في</th>
                        <th class="p-3.5">إجراءات التحكم</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($stores as $st)
                    @php
                        $owner = $st->users->first(fn ($u) => $u->hasRole('owner')) ?? $st->users->first();
                        $isExpired = $st->isSubscriptionExpired();
                        $typeBadgeColors = [
                            'grocery' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                            'clothing' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                            'perfumes' => 'bg-amber-50 text-amber-800 border-amber-200',
                            'general' => 'bg-slate-100 text-slate-700 border-slate-200',
                        ];
                    @endphp
                    <tr class="hover:bg-slate-50/60">
                        <td class="p-3.5">
                            <div class="font-bold text-slate-900 text-sm">{{ $st->name }}</div>
                            <div class="text-[11px] text-slate-400 flex items-center gap-2 mt-0.5">
                                <span class="font-mono">{{ $st->slug }}</span>
                                <span>• {{ $st->currency_code }} ({{ $st->currency_symbol }})</span>
                                @if(!$st->onboarding_completed_at)
                                    <span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 font-semibold">بانتظار التهيئة</span>
                                @endif
                            </div>
                        </td>

                        <td class="p-3.5">
                            <span class="px-2.5 py-1 rounded-lg border font-bold {{ $typeBadgeColors[$st->business_type] ?? $typeBadgeColors['general'] }}">
                                {{ $st->businessTypeLabel() }}
                            </span>
                        </td>

                        <td class="p-3.5">
                            @if($owner)
                                <div class="font-semibold text-slate-900">{{ $owner->name }}</div>
                                <div class="text-[11px] text-slate-500 font-mono" dir="ltr">{{ $owner->email }}</div>
                                @if($owner->phone)
                                    <div class="text-[11px] text-slate-500" dir="ltr">{{ $owner->phone }}</div>
                                @endif
                            @else
                                <span class="text-slate-400">بدون مالك</span>
                            @endif
                        </td>

                        <td class="p-3.5">
                            <div>الأجهزة: <strong>{{ format_qty($st->terminals_count) }}</strong></div>
                            <div class="text-slate-500 mt-0.5">الفواتير: <strong>{{ format_qty($st->orders_count) }}</strong></div>
                        </td>

                        <td class="p-3.5">
                            @if($st->subscription_status === 'suspended')
                                <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 font-bold">موقوف</span>
                            @elseif($isExpired)
                                <span class="px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 font-bold">منتهي الصلاحية</span>
                            @elseif($st->subscription_status === 'trial')
                                <span class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 font-bold">تجريبي</span>
                            @else
                                <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold">نشط</span>
                            @endif
                        </td>

                        <td class="p-3.5 font-semibold {{ $isExpired ? 'text-rose-600' : 'text-slate-700' }}">
                            {{ $st->subscription_ends_at ? $st->subscription_ends_at->format('Y-m-d') : 'مفتوح' }}
                        </td>

                        <td class="p-3.5">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <form method="POST" action="{{ route('super-admin.stores.toggle-status', $st->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="px-2.5 py-1.5 rounded-lg font-bold {{ $st->subscription_status === 'suspended' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200' }}">
                                        {{ $st->subscription_status === 'suspended' ? 'إعادة تفعيل' : 'إيقاف مؤقت' }}
                                    </button>
                                </form>

                                <button type="button"
                                        @click="extendTarget = { id: {{ $st->id }}, name: @js($st->name), ends_at: @js($st->subscription_ends_at?->format('Y-m-d') ?? '') }"
                                        class="px-2.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 font-bold">
                                    تمديد الاشتراك
                                </button>

                                @if($owner)
                                <button type="button"
                                        @click="resetTarget = { id: {{ $st->id }}, name: @js($st->name), owner_email: @js($owner->email) }"
                                        class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold">
                                    كلمة المرور
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-slate-400">
                            لا توجد متاجر مطابقة لمعايير البحث الحالية.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3.5 border-t border-slate-100">{{ $stores->links() }}</div>
    </div>

    <!-- Modal 1: Create Store + Owner Account -->
    <div x-show="createModal" style="display: none;"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4 overflow-y-auto">
        <form method="POST" action="{{ route('super-admin.stores.store') }}"
              @click.outside="createModal = false"
              class="bg-white rounded-2xl max-w-2xl w-full p-6 space-y-4 text-xs shadow-2xl my-8">
            @csrf
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div>
                    <h3 class="font-bold text-base text-slate-900">إنشاء متجر جديد وحساب المالك</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">سيتم توجيه التاجر تلقائياً لمعالج التهيئة السريعة (60 ثانية) عند أول دخول</p>
                </div>
                <button type="button" @click="createModal = false" class="text-slate-400 hover:text-slate-700 text-base">✕</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="sm:col-span-2 font-bold text-emerald-800 border-b border-slate-100 pb-1">1. بيانات المتجر والاشتراك</div>

                <div>
                    <label class="block font-semibold mb-1">اسم المتجر / المحل *</label>
                    <input type="text" name="store_name" value="{{ old('store_name') }}" required placeholder="مثال: سوبرماركت النجم"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2">
                </div>

                <div>
                    <label class="block font-semibold mb-1">نوع النشاط *</label>
                    <select name="business_type" class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white">
                        <option value="grocery" @selected(old('business_type') === 'grocery')>🛒 بقالة / سوبرماركت</option>
                        <option value="clothing" @selected(old('business_type') === 'clothing')>👕 ملابس وأحذية</option>
                        <option value="perfumes" @selected(old('business_type') === 'perfumes')>🧴 عطور وتجميل</option>
                        <option value="general" @selected(old('business_type') === 'general')>🏪 تجزئة عامة</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold mb-1">العملة الأساسية</label>
                    <select name="currency_code" class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white">
                        <option value="YER" @selected(old('currency_code', 'YER') === 'YER')>ريال يمني (YER)</option>
                        <option value="SAR" @selected(old('currency_code') === 'SAR')>ريال سعودي (SAR)</option>
                        <option value="USD" @selected(old('currency_code') === 'USD')>دولار أمريكي (USD)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block font-semibold mb-1">رمز العملة</label>
                        <input type="text" name="currency_symbol" value="{{ old('currency_symbol', 'ر.ي') }}"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">الكسور العشرية</label>
                        <select name="decimal_places" class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white">
                            <option value="0" @selected((int) old('decimal_places', 0) === 0)>0 (بدون كسور)</option>
                            <option value="2" @selected((int) old('decimal_places') === 2)>2 (0.00)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold mb-1">حالة الاشتراك الابتدائية *</label>
                    <select name="subscription_status" class="w-full border border-slate-300 rounded-xl px-3 py-2 bg-white">
                        <option value="active" @selected(old('subscription_status', 'active') === 'active')>نشط مدفوع (Active)</option>
                        <option value="trial" @selected(old('subscription_status') === 'trial')>فترة تجريبية (Trial)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-semibold mb-1">تاريخ انتهاء الاشتراك (اختياري)</label>
                    <input type="date" name="subscription_ends_at" value="{{ old('subscription_ends_at', now()->addYear()->format('Y-m-d')) }}"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2">
                </div>

                <div class="sm:col-span-2 font-bold text-emerald-800 border-b border-slate-100 pb-1 pt-2">2. بيانات مالك المتجر (حساب الإدارة)</div>

                <div>
                    <label class="block font-semibold mb-1">اسم المالك الكامل *</label>
                    <input type="text" name="owner_name" value="{{ old('owner_name') }}" required placeholder="الاسم الثلاثي"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2">
                </div>

                <div>
                    <label class="block font-semibold mb-1">رقم الجوال / واتساب</label>
                    <input type="text" name="owner_phone" value="{{ old('owner_phone') }}" placeholder="77XXXXXXX"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2" dir="ltr">
                </div>

                <div>
                    <label class="block font-semibold mb-1">البريد الإلكتروني للدخول *</label>
                    <input type="email" name="owner_email" value="{{ old('owner_email') }}" required placeholder="owner@store.ye"
                           class="w-full border border-slate-300 rounded-xl px-3 py-2" dir="ltr">
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div class="col-span-2">
                        <label class="block font-semibold mb-1">كلمة المرور (8+ أحرف) *</label>
                        <input type="text" name="owner_password" value="{{ old('owner_password', 'YemenPos@' . rand(1000, 9999)) }}" required minlength="8"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 font-mono" dir="ltr">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">رمز PIN</label>
                        <input type="text" name="owner_pin" value="{{ old('owner_pin', '1234') }}" maxlength="4" pattern="\d{4}"
                               class="w-full border border-slate-300 rounded-xl px-3 py-2 text-center font-mono" dir="ltr">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" @click="createModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">إلغاء</button>
                <button type="submit" class="px-6 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                    إنشاء المتجر وتوليد بطاقة الدخول ✓
                </button>
            </div>
        </form>
    </div>

    <!-- Modal 2: Extend Subscription -->
    <div x-show="extendTarget !== null" style="display: none;"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4">
        <div @click.outside="extendTarget = null" class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 text-xs shadow-2xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-bold text-sm">تمديد اشتراك: <span class="text-emerald-700" x-text="extendTarget?.name"></span></h3>
                <button type="button" @click="extendTarget = null" class="text-slate-400">✕</button>
            </div>

            <div class="space-y-2">
                <div class="font-semibold text-slate-600">تمديد فوري بضغطة واحدة (مع تفعيل المتجر تلقائياً):</div>
                <div class="grid grid-cols-3 gap-2">
                    <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`">
                        @csrf
                        <input type="hidden" name="preset" value="+1 month">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 font-bold">
                            + شهر واحد
                        </button>
                    </form>
                    <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`">
                        @csrf
                        <input type="hidden" name="preset" value="+3 months">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 font-bold">
                            + 3 أشهر
                        </button>
                    </form>
                    <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`">
                        @csrf
                        <input type="hidden" name="preset" value="+1 year">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">
                            + سنة كاملة
                        </button>
                    </form>
                </div>
            </div>

            <form method="POST" :action="`/super-admin/stores/${extendTarget?.id}/extend-subscription`" class="space-y-3 pt-3 border-t">
                @csrf
                <div>
                    <label class="block font-semibold mb-1">أو تحديد تاريخ انتهاء مخصص:</label>
                    <input type="date" name="subscription_ends_at" :value="extendTarget?.ends_at" required
                           class="w-full border border-slate-300 rounded-xl px-3 py-2">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="extendTarget = null" class="px-3.5 py-1.5 rounded-lg border">إلغاء</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-slate-900 text-white font-bold">حفظ التاريخ المخصص</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Reset Store Owner Password -->
    <div x-show="resetTarget !== null" style="display: none;"
         class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4">
        <form method="POST" :action="`/super-admin/stores/${resetTarget?.id}/reset-owner-password`"
              @click.outside="resetTarget = null"
              class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 text-xs shadow-2xl">
            @csrf
            <div class="flex items-center justify-between border-b pb-3">
                <div>
                    <h3 class="font-bold text-sm">إعادة تعيين كلمة مرور المالك</h3>
                    <div class="text-[11px] text-slate-500 mt-0.5">المتجر: <span x-text="resetTarget?.name"></span> (<span dir="ltr" x-text="resetTarget?.owner_email"></span>)</div>
                </div>
                <button type="button" @click="resetTarget = null" class="text-slate-400">✕</button>
            </div>

            <div>
                <label class="block font-semibold mb-1">كلمة المرور الجديدة (8 أحرف على الأقل) *</label>
                <input type="text" name="password" required minlength="8" placeholder="أدخل كلمة المرور الجديدة"
                       class="w-full border border-slate-300 rounded-xl px-3 py-2 font-mono" dir="ltr">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="resetTarget = null" class="px-3.5 py-1.5 rounded-lg border">إلغاء</button>
                <button type="submit" class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold">تحديث كلمة المرور</button>
            </div>
        </form>
    </div>

</div>
@endsection
