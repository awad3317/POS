<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تهيئة المتجر السريعة — Yemen POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 py-8 px-4 font-sans antialiased">
<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8"
     x-data="{ step: 1, businessType: '{{ old('business_type', $store->business_type ?? 'grocery') }}', paperWidth: '{{ old('paper_width', '80mm') }}' }">

    <div class="mb-6">
        <span class="text-xs font-bold text-emerald-600">معالج التهيئة في 60 ثانية</span>
        <h1 class="text-2xl font-bold text-slate-900 mt-1">لنجهّز متجرك للعمل فوراً</h1>
        <div class="grid grid-cols-3 gap-2 mt-4">
            <div :class="step >= 1 ? 'bg-emerald-600' : 'bg-slate-200'" class="h-1.5 rounded-full"></div>
            <div :class="step >= 2 ? 'bg-emerald-600' : 'bg-slate-200'" class="h-1.5 rounded-full"></div>
            <div :class="step >= 3 ? 'bg-emerald-600' : 'bg-slate-200'" class="h-1.5 rounded-full"></div>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
            @foreach($errors->all() as $e) <div>• {{ $e }}</div> @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('onboarding.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <!-- Step 1: Store Identity & Currency -->
        <div x-show="step === 1" class="space-y-4">
            <h2 class="text-base font-bold text-slate-800">1. بيانات المحل والعملة</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold mb-1">اسم المحل / المتجر *</label>
                    <input type="text" name="name" value="{{ old('name', $store->name) }}" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">رقم الهاتف / واتساب</label>
                    <input type="text" name="phone" value="{{ old('phone', $store->phone) }}" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">العملة الأساسية</label>
                    <select name="currency_code" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                        <option value="YER" @selected(old('currency_code', $store->currency_code) === 'YER')>ريال يمني (ر.ي)</option>
                        <option value="SAR" @selected(old('currency_code', $store->currency_code) === 'SAR')>ريال سعودي (ر.س)</option>
                        <option value="USD" @selected(old('currency_code', $store->currency_code) === 'USD')>دولار أمريكي ($)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">الخانات العشرية للعرض</label>
                    <select name="decimal_places" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                        <option value="0" @selected((int) old('decimal_places', $store->decimal_places) === 0)>بدون كسور (0 — مناسب للريال اليمني)</option>
                        <option value="2" @selected((int) old('decimal_places', $store->decimal_places) === 2)>خانتان عشريتان (0.00)</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold mb-1">العنوان</label>
                    <input type="text" name="address" value="{{ old('address', $store->address) }}" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold mb-1">شعار المحل (اختياري)</label>
                    <input type="file" name="logo" accept="image/*" class="w-full text-xs">
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" @click="step = 2" class="px-5 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold">التالي ←</button>
            </div>
        </div>

        <!-- Step 2: Business Type -->
        <div x-show="step === 2" style="display:none;" class="space-y-4">
            <h2 class="text-base font-bold text-slate-800">2. اختر نوع نشاطك التجاري</h2>
            <input type="hidden" name="business_type" :value="businessType">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="button" @click="businessType = 'grocery'"
                        :class="businessType === 'grocery' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'"
                        class="p-4 rounded-xl border-2 text-right">
                    <div class="font-bold text-sm">🛒 بقالة / سوبرماركت / عطارة</div>
                    <div class="text-xs text-slate-600 mt-1">يفعّل الوحدات المتعددة (حبة/كرتون)، باركود الميزان، والأزرار السريعة.</div>
                </button>
                <button type="button" @click="businessType = 'clothing'"
                        :class="businessType === 'clothing' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'"
                        class="p-4 rounded-xl border-2 text-right">
                    <div class="font-bold text-sm">👕 ملابس / أحذية / إكسسوارات</div>
                    <div class="text-xs text-slate-600 mt-1">يفعّل المقاسات والألوان، ملصقات الباركود، وتحديث سعر الصرف.</div>
                </button>
                <button type="button" @click="businessType = 'perfumes'"
                        :class="businessType === 'perfumes' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'"
                        class="p-4 rounded-xl border-2 text-right">
                    <div class="font-bold text-sm">🧴 عطور ومستحضرات تجميل</div>
                    <div class="text-xs text-slate-600 mt-1">يفعّل الأحجام والتركيزات وسعر الصرف والأزرار السريعة.</div>
                </button>
                <button type="button" @click="businessType = 'general'"
                        :class="businessType === 'general' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'"
                        class="p-4 rounded-xl border-2 text-right">
                    <div class="font-bold text-sm">🏪 تجزئة عامة / قطع غيار / مكتبة</div>
                    <div class="text-xs text-slate-600 mt-1">واجهة مبسطة وسريعة تناسب أي محل تجاري.</div>
                </button>
            </div>
            <div class="flex justify-between pt-2">
                <button type="button" @click="step = 1" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">السابق</button>
                <button type="button" @click="step = 3" class="px-5 py-2 rounded-xl bg-emerald-600 text-white text-sm font-bold">التالي ←</button>
            </div>
        </div>

        <!-- Step 3: Thermal Receipt Setup -->
        <div x-show="step === 3" style="display:none;" class="space-y-4">
            <h2 class="text-base font-bold text-slate-800">3. إعداد الفاتورة الحرارية</h2>
            <input type="hidden" name="paper_width" :value="paperWidth">
            <div class="grid grid-cols-2 gap-3">
                <button type="button" @click="paperWidth = '80mm'"
                        :class="paperWidth === '80mm' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'"
                        class="p-3 rounded-xl border-2 text-center font-bold text-sm">طابعة حرارية 80mm (قياسي)</button>
                <button type="button" @click="paperWidth = '58mm'"
                        :class="paperWidth === '58mm' ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200'"
                        class="p-3 rounded-xl border-2 text-center font-bold text-sm">طابعة حرارية 58mm (صغير)</button>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">عبارة أعلى الفاتورة</label>
                <input type="text" name="header_note" value="{{ old('header_note', 'أهلاً بكم في متجرنا') }}" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">عبارة أسفل الفاتورة</label>
                <input type="text" name="footer_note" value="{{ old('footer_note', 'شكراً لزيارتكم!') }}" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
            </div>
            <div class="flex justify-between pt-2">
                <button type="button" @click="step = 2" class="px-4 py-2 rounded-xl border border-slate-300 text-sm">السابق</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">إنهاء التهيئة وبدء العمل ✓</button>
            </div>
        </div>
    </form>
</div>
</body>
</html>
