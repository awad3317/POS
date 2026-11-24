<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>شاشة الكاشير — {{ $store->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/pos.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 font-sans antialiased select-none"
      x-data="posApp(@js($bootConfig))"
      x-init="init()"
      @keydown.window="handleGlobalKeydown($event)">

    <!-- Toast Notification -->
    <div x-show="toast.show" x-transition style="display: none;"
         class="fixed bottom-4 left-4 z-50 max-w-md px-4 py-3 rounded-xl shadow-lg border text-sm font-bold flex items-center gap-2"
         :class="{
            'bg-emerald-600 text-white border-emerald-700': toast.type === 'success',
            'bg-rose-600 text-white border-rose-700': toast.type === 'error',
            'bg-amber-500 text-slate-950 border-amber-600': toast.type === 'warning',
         }">
        <span x-text="toast.message"></span>
        <button @click="toast.show = false" class="opacity-80 hover:opacity-100 px-1">✕</button>
    </div>

    <!-- Terminal Activation Screen (shown if terminal_token missing) -->
    <template x-if="initialized && !terminalToken">
        <div class="min-h-screen flex items-center justify-center p-4 bg-slate-900">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 sm:p-8 space-y-5">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 text-white font-bold text-xl mb-3">POS</div>
                    <h1 class="text-xl font-bold text-slate-900">تفعيل جهاز الكاشير للعمل أوفلاين</h1>
                    <p class="text-xs text-slate-500 mt-1">{{ $store->name }} — اربط هذا الجهاز ليعمل حتى عند انقطاع الإنترنت</p>
                </div>

                <div x-show="activationError" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold" x-text="activationError"></div>

                <form @submit.prevent="activateTerminal()" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">البريد الإلكتروني أو رقم الجوال</label>
                        <input type="text" x-model="activationForm.login" required
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">رمز جهاز الكاشير (Terminal Code)</label>
                        @if($terminals->count() > 0)
                            <select x-model="activationForm.terminal_code" class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 mb-2 focus:border-emerald-600 focus:outline-none">
                                @foreach($terminals as $t)
                                    <option value="{{ $t->code }}">{{ $t->code }} — {{ $t->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" x-model="activationForm.terminal_code" placeholder="T01" required
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-emerald-600 focus:outline-none">
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">كلمة المرور *</label>
                        <input type="password" x-model="activationForm.password" required autofocus
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-emerald-600 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">رمز القفل السريع PIN (4 أرقام - اختياري)</label>
                        <input type="password" inputmode="numeric" maxlength="4" x-model="activationForm.quick_pin" placeholder="مثال: 1234"
                               class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 focus:border-emerald-600 focus:outline-none">
                    </div>

                    <button type="submit" :disabled="activationLoading"
                            class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-bold transition">
                        <span x-show="!activationLoading">تفعيل الجهاز وتحميل الأصناف ⚡</span>
                        <span x-show="activationLoading">جاري التفعيل والمزامنة...</span>
                    </button>
                </form>

                <div class="text-center pt-2 border-t border-slate-100">
                    <a href="{{ route('dashboard') }}" class="text-xs text-slate-500 hover:text-slate-800">← العودة إلى لوحة الإدارة</a>
                </div>
            </div>
        </div>
    </template>

    <!-- Cashier PIN Lock Overlay (never clears cart) -->
    <div x-show="isLocked" x-transition.opacity style="display: none;"
         class="fixed inset-0 z-50 bg-slate-950/95 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-white rounded-2xl p-6 text-center space-y-4 shadow-2xl">
            <div class="text-4xl">🔒</div>
            <h2 class="text-lg font-bold text-slate-900">الشاشة مقفلة مؤقتاً</h2>
            <p class="text-xs text-slate-500">السلة الحالية محفوظة بأمان (<span x-text="cart.length"></span> صنف)</p>

            <div x-show="lockError" class="p-2.5 rounded-xl bg-rose-50 text-rose-700 text-xs font-semibold" x-text="lockError"></div>

            <form x-show="!unlockPasswordMode" @submit.prevent="unlockWithPin()" class="space-y-3">
                <input type="password" inputmode="numeric" maxlength="4" x-model="lockPinInput" placeholder="أدخل رمز PIN (4 أرقام)" autofocus
                       class="w-full text-center text-2xl tracking-[0.6em] font-bold rounded-xl border border-slate-300 py-3 focus:border-emerald-600 focus:outline-none">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm">فتح الشاشة</button>
            </form>

            <form x-show="unlockPasswordMode" @submit.prevent="unlockWithPasswordOnline()" class="space-y-3">
                <input type="password" x-model="unlockPasswordInput" placeholder="كلمة المرور (يتطلب إنترنت)"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-slate-900 text-white font-bold text-sm">تحقق وفتح</button>
            </form>

            <button type="button" @click="unlockPasswordMode = !unlockPasswordMode; lockError = ''"
                    class="text-xs text-slate-500 hover:text-slate-800 underline">
                <span x-text="unlockPasswordMode ? 'العودة لرمز PIN' : 'نسيت PIN؟ فتح بكلمة المرور'"></span>
            </button>
        </div>
    </div>

    <!-- Main POS Workspace -->
    <div x-show="initialized && terminalToken" style="display: none;" class="min-h-screen flex flex-col">

        <!-- Top Bar -->
        <header class="bg-slate-900 text-white px-3 lg:px-5 py-2.5 flex flex-wrap items-center justify-between gap-2 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-lg bg-emerald-600 text-xs font-bold" x-text="terminalCode || 'T01'"></span>
                    <div>
                        <div class="font-bold text-sm leading-tight" x-text="storeSettings.name || '{{ $store->name }}'"></div>
                        <div class="text-[11px] text-slate-400">الكاشير: <span x-text="userInfo?.name || '{{ $user->name }}'"></span></div>
                    </div>
                </div>

                <!-- Connection Pill -->
                <span class="px-2.5 py-1 rounded-full text-xs font-bold flex items-center gap-1.5"
                      :class="isOnline ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'">
                    <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-emerald-400' : 'bg-rose-400 animate-pulse'"></span>
                    <span x-text="isOnline ? 'متصل' : 'أوفلاين'"></span>
                </span>

                <!-- Outbox Counter & Sync Status -->
                <button type="button" @click="openOutboxModal()"
                        class="px-2.5 py-1 rounded-full text-xs font-bold border transition flex items-center gap-1.5"
                        :class="outboxCount > 0 ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-slate-800 text-slate-300 border-slate-700'">
                    <span>طابور المزامنة:</span>
                    <span x-text="outboxCount"></span>
                    <span x-show="syncErrors.length > 0" class="text-rose-400 font-extrabold">(!)</span>
                </button>

                <!-- Shift Status Pill -->
                <button type="button" @click="openShiftModal()"
                        class="px-2.5 py-1 rounded-full text-xs font-bold border transition"
                        :class="activeShift ? 'bg-sky-500/20 text-sky-300 border-sky-500/40' : 'bg-rose-500/20 text-rose-300 border-rose-500/40'">
                    <span x-show="activeShift">الوردية مفتوحة • الدرج: <strong x-text="fmtMoney(activeShift?.expected_cash || 0)"></strong> <span x-text="currencySymbol()"></span></span>
                    <span x-show="!activeShift">⚠️ افتح الوردية</span>
                </button>
            </div>

            <div class="flex items-center gap-2 flex-wrap text-xs">
                <button type="button" @click="syncNow(true)" :disabled="isSyncing"
                        class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 font-bold flex items-center gap-1">
                    <span x-text="isSyncing ? 'جاري المزامنة...' : '🔄 مزامنة الآن'"></span>
                </button>

                <button type="button" @click="openDrawerModal()"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 font-semibold">
                    💸 حركات الدرج
                </button>

                <button type="button" @click="openHeldCartsModal()"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 font-semibold">
                    ⏸️ معلق (<span x-text="heldCarts.length"></span>) <span class="text-slate-400 text-[10px]">F4</span>
                </button>

                <button type="button" @click="openBackupModal()"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 font-semibold">
                    🔐 نسخة طوارئ
                </button>

                <button type="button" @click="openOrTogglePinLock()"
                        class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 font-bold">
                    🔒 قفل PIN
                </button>

                <a href="{{ route('dashboard') }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300">
                    الإدارة
                </a>
            </div>
        </header>

        <!-- Token Revoked Alert -->
        <div x-show="authInvalidWarning" style="display: none;"
             class="bg-rose-600 text-white px-4 py-2 text-xs font-bold flex items-center justify-between">
            <span>⚠️ انتهت جلسة هذا الجهاز أو تم إيقافه من الإدارة. يمكنك تصدير نسخة طوارئ مشفرة قبل إعادة التفعيل.</span>
            <button type="button" @click="deactivateTerminalPrompt()" class="px-3 py-1 rounded bg-white text-rose-700">إعادة تفعيل الجهاز</button>
        </div>

        <!-- Split Workspace: Right = Catalog / Search / Quick Pick, Left = Active Cart & Checkout -->
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-3 p-3 overflow-hidden">

            <!-- Catalog, Barcode & Quick Pick Column -->
            <section class="lg:col-span-6 xl:col-span-7 flex flex-col gap-3 min-h-0">

                <!-- Auto-Focus Barcode + Instant Search -->
                <div class="bg-white rounded-2xl border border-slate-200 p-3 shadow-sm grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[11px] font-bold text-emerald-700 mb-1">مسح الباركود (تركيز دائم) + Enter</label>
                        <input type="text"
                               x-ref="barcodeInput"
                               x-model="barcodeInput"
                               @keydown.enter.prevent="handleBarcodeSubmit()"
                               placeholder="امسح باركود الصنف / الميزان / الوحدة..."
                               class="w-full rounded-xl border-2 border-emerald-500 bg-emerald-50/40 px-3.5 py-2.5 text-sm font-bold focus:outline-none focus:bg-white">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[11px] font-bold text-slate-600">بحث فوري بالاسم أو الكود</label>
                            <span x-show="searchQuery" class="text-[10px] text-emerald-600 font-semibold" x-text="`${searchResults.length} نتيجة`"></span>
                        </div>
                        <div class="relative">
                            <input type="text"
                                   x-model="searchQuery"
                                   placeholder="ابحث بالاسم، SKU، أو الباركود..."
                                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm focus:border-slate-800 focus:outline-none">
                            <button x-show="searchQuery" @click="searchQuery = ''; focusBarcodeInput()" type="button"
                                    class="absolute left-2.5 top-2.5 text-slate-400 hover:text-slate-700 text-xs">✕</button>
                        </div>
                    </div>
                </div>

                <!-- Categories Filter Bar -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <button type="button" @click="selectedCategoryId = null"
                            :class="selectedCategoryId === null ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border-slate-200'"
                            class="px-3.5 py-1.5 rounded-xl border text-xs font-bold shrink-0 transition">
                        الكل (<span x-text="products.length"></span>)
                    </button>
                    <template x-for="cat in categories" :key="cat.id">
                        <button type="button" @click="selectedCategoryId = cat.id"
                                :class="Number(selectedCategoryId) === Number(cat.id) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200'"
                                class="px-3.5 py-1.5 rounded-xl border text-xs font-bold shrink-0 transition"
                                x-text="cat.name"></button>
                    </template>
                </div>

                <!-- Instant Search Results (max 20) OR Quick-Pick Grid -->
                <div class="flex-1 bg-white rounded-2xl border border-slate-200 p-3 shadow-sm overflow-y-auto space-y-4">

                    <!-- Search Results -->
                    <div x-show="searchQuery || selectedCategoryId">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xs font-bold text-slate-700">نتائج البحث السريع (بحد أقصى 20)</h3>
                            <span class="text-[11px] text-slate-400" x-text="`${searchResults.length} صنف`"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2">
                            <template x-for="prod in searchResults" :key="prod.id">
                                <button type="button" @click="selectProductFromGrid(prod)"
                                        class="text-right p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/40 transition flex flex-col justify-between gap-1.5">
                                    <div class="font-bold text-xs text-slate-800 line-clamp-2" x-text="prod.name"></div>
                                    <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-100">
                                        <span class="font-extrabold text-emerald-700" x-text="`${fmtMoney(prod.sale_price)} ${currencySymbol()}`"></span>
                                        <span class="text-slate-400" x-text="`م: ${fmtQty(prod.quantity)}`"></span>
                                    </div>
                                </button>
                            </template>
                        </div>
                        <div x-show="searchResults.length === 0" class="text-center py-6 text-xs text-slate-400">
                            لا توجد أصناف مطابقة للبحث.
                        </div>
                    </div>

                    <!-- Quick Pick Grid (No-Barcode Items) -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-xs font-bold text-amber-800">⚡ أصناف البيع السريع (بدون باركود)</h3>
                            <span class="text-[11px] text-slate-400" x-text="`${quickPickProducts.length} صنف`"></span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5">
                            <template x-for="qp in quickPickProducts" :key="qp.id">
                                <button type="button" @click="selectProductFromGrid(qp)"
                                        class="p-3 rounded-xl bg-amber-50/70 hover:bg-amber-100 border border-amber-200 text-right transition flex flex-col justify-between gap-1.5">
                                    <div class="font-bold text-xs text-slate-900" x-text="qp.name"></div>
                                    <div class="flex items-center justify-between text-xs font-extrabold text-amber-900">
                                        <span x-text="`${fmtMoney(qp.sale_price)} ${currencySymbol()}`"></span>
                                        <span class="text-[10px] font-medium text-amber-700">+ إضافة</span>
                                    </div>
                                </button>
                            </template>
                        </div>
                        <div x-show="quickPickProducts.length === 0 && !searchQuery && !selectedCategoryId" class="text-center py-10 text-xs text-slate-400">
                            امسح باركود الصنف أو ابحث بالاسم، أو فعّل خيار "بيع سريع" لأي صنف من لوحة المنتجات.
                        </div>
                    </div>
                </div>
            </section>

            <!-- Active Cart & Checkout Column -->
            <section class="lg:col-span-6 xl:col-span-5 bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col min-h-0 overflow-hidden">

                <!-- Cart Header & Selected Customer -->
                <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between gap-2 bg-slate-50">
                    <div>
                        <h2 class="font-bold text-sm text-slate-900">الفاتورة الحالية (<span x-text="cart.length"></span>)</h2>
                        <div class="text-[11px] text-slate-500">
                            العميل:
                            <strong class="text-slate-800" x-text="selectedCustomer ? selectedCustomer.name : 'عميل نقدي'"></strong>
                            <button x-show="selectedCustomer" @click="selectedCustomer = null" type="button" class="text-rose-600 mr-1 text-[10px]">(إلغاء)</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="openPaymentModal()"
                                class="px-2.5 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-bold">
                            👤 عميل / دفع (F8)
                        </button>
                    </div>
                </div>

                <!-- Cost Override Warning -->
                <div x-show="cartComputation.requiresCostOverride" style="display: none;"
                     class="px-4 py-2 bg-amber-50 border-b border-amber-200 text-amber-900 text-xs font-bold flex items-center justify-between">
                    <span>⚠️ تنبيه: السعر بعد الخصم أقل من التكلفة المسموحة.</span>
                    <span x-show="!canOverrideDiscounts()" class="text-[11px] bg-amber-200 px-2 py-0.5 rounded">يتطلب رمز المدير</span>
                </div>

                <!-- Cart Lines -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                    <template x-for="(line, idx) in cart" :key="line.lineKey">
                        <div @click="selectedLineIndex = idx"
                             :class="selectedLineIndex === idx ? 'bg-emerald-50/50' : 'hover:bg-slate-50'"
                             class="px-3 py-2.5 transition cursor-pointer">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-xs text-slate-900 truncate" x-text="line.product_name"></div>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap text-[11px] text-slate-500">
                                        <span>السعر: <strong class="text-slate-700" x-text="fmtMoney(line.unit_price)"></strong></span>

                                        <!-- Unit switcher if multi-units exist -->
                                        <template x-if="(line.available_units || []).length > 0">
                                            <select @change="changeLineUnit(idx, $event.target.value)"
                                                    class="border border-slate-300 rounded-lg px-1.5 py-0.5 text-[11px] bg-white">
                                                <template x-if="!line.available_units.some(u => Boolean(u.is_default))">
                                                    <option value="" :selected="!line.unit_id">حبة (أساسي)</option>
                                                </template>
                                                <template x-for="u in line.available_units" :key="u.id">
                                                    <option :value="u.id" :selected="Number(u.id) === Number(line.unit_id)" x-text="`${u.unit_name} (×${fmtQty(u.conversion_factor)})`"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="(line.available_units || []).length === 0">
                                            <span class="px-1.5 py-0.5 rounded bg-slate-100" x-text="line.unit_name_snapshot"></span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Line Net Total & Remove -->
                                <div class="text-left shrink-0 flex items-center gap-2">
                                    <div class="font-extrabold text-sm text-emerald-700"
                                         x-text="fmtMoney(cartComputation.lines[idx]?.lineNet || 0)"></div>
                                    <button type="button" @click.stop="removeCartItem(idx)"
                                            class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 text-xs" title="حذف الصنف">🗑️</button>
                                </div>
                            </div>

                            <!-- Quantity Controls & Item Discount -->
                            <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                <div class="flex items-center gap-1">
                                    <button type="button" @click.stop="adjustLineQty(idx, -1)"
                                            class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 font-bold">-</button>
                                    <input type="number" step="0.001" min="0.001" x-model="line.quantity" @click.stop
                                           class="w-20 text-center rounded-lg border border-slate-300 py-1 font-bold text-xs bg-white">
                                    <button type="button" @click.stop="adjustLineQty(idx, 1)"
                                            class="w-7 h-7 rounded-lg border border-slate-300 bg-white hover:bg-slate-100 font-bold">+</button>
                                </div>

                                <div class="flex items-center gap-1">
                                    <span class="text-[11px] text-slate-500">خصم الصنف:</span>
                                    <input type="number" step="0.01" min="0" x-model="line.discount_amount" @click.stop
                                           class="w-20 text-center rounded-lg border border-slate-300 py-1 text-xs bg-white">
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="cart.length === 0" class="py-16 text-center text-slate-400 text-xs space-y-1">
                        <div class="text-3xl">🛒</div>
                        <div>السلة فارغة — امسح باركود أو اختر صنفاً للبدء</div>
                        <div class="text-[11px] text-slate-300">F2 دفع نقدي سريع • F4 تعليق • F8 دفع متعدد • +/- كمية</div>
                    </div>
                </div>

                <!-- Totals & Primary Action Buttons -->
                <div class="p-3.5 border-t border-slate-200 bg-slate-50 space-y-3">
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-xl border border-slate-200">
                            <span class="text-slate-500">المجموع:</span>
                            <strong x-text="fmtMoney(cartComputation.subtotal)"></strong>
                        </div>
                        <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-xl border border-slate-200">
                            <span class="text-slate-500">خصم إضافي:</span>
                            <input type="number" min="0" step="0.01" x-model="orderDiscountAmount"
                                   class="w-20 text-left font-bold border-b border-slate-300 focus:outline-none text-xs">
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-1">
                        <div class="text-xs text-slate-500">
                            <span>ضريبة: <strong x-text="fmtMoney(cartComputation.taxTotal)"></strong></span>
                        </div>
                        <div class="text-left">
                            <span class="text-xs text-slate-500 ml-1">الصافي للدفع:</span>
                            <span class="text-2xl font-extrabold text-slate-900" x-text="fmtMoney(cartComputation.totalAmount)"></span>
                            <span class="text-xs font-bold text-slate-600" x-text="currencySymbol()"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-2">
                        <button type="button" @click="quickCashCheckout()" :disabled="cart.length === 0"
                                class="col-span-7 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold text-sm shadow-sm transition">
                            💵 دفع نقدي سريع (F2)
                        </button>
                        <button type="button" @click="openPaymentModal()" :disabled="cart.length === 0"
                                class="col-span-5 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white font-bold text-sm shadow-sm transition">
                            💳 متعدد / آجل (F8)
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- MODAL BACKDROP -->
    <div x-show="modal !== null" x-transition.opacity style="display: none;"
         class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-[1px] flex items-center justify-center p-4"
         @click.self="closeModal()">

        <!-- Variant Picker Modal -->
        <div x-show="modal === 'variant_picker'" class="w-full max-w-md bg-white rounded-2xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-sm" x-text="variantPickerData.product?.name || 'اختر المقاس / اللون'"></h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-2 max-h-80 overflow-y-auto">
                <template x-for="v in variantPickerData.variants" :key="v.id">
                    <button type="button" @click="pickVariantForCart(v)"
                            class="p-3 rounded-xl border border-slate-200 hover:border-emerald-600 hover:bg-emerald-50 text-right">
                        <div class="font-bold text-xs text-slate-900" x-text="v.attribute_label || `${v.size} - ${v.color}`"></div>
                        <div class="flex justify-between text-[11px] mt-1">
                            <span class="font-bold text-emerald-700" x-text="`${fmtMoney(v.sale_price)} ${currencySymbol()}`"></span>
                            <span class="text-slate-400" x-text="`م: ${fmtQty(v.quantity)}`"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <!-- Shift Open / Close Modal -->
        <div x-show="modal === 'shift'" class="w-full max-w-md bg-white rounded-2xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-bold text-base" x-text="activeShift ? 'إغلاق الوردية (تقرير Z)' : 'فتح وردية كاشير جديدة'"></h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <template x-if="!activeShift">
                <form @submit.prevent="openShiftLocal()" class="space-y-3 text-sm">
                    <div>
                        <label class="block text-xs font-semibold mb-1">النقد الافتتاحي بالدرج (<span x-text="currencySymbol()"></span>)</label>
                        <input type="number" step="0.01" min="0" x-model="shiftOpeningCash" required autofocus
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-bold text-base">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">ملاحظات (اختياري)</label>
                        <input type="text" x-model="shiftNotes" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold">فتح الوردية الآن</button>
                </form>
            </template>

            <template x-if="activeShift">
                <form @submit.prevent="closeShiftLocal()" class="space-y-3 text-xs">
                    <div class="bg-slate-50 rounded-xl p-3 space-y-1.5 border border-slate-200">
                        <div class="flex justify-between"><span>الافتتاح:</span><strong x-text="fmtMoney(activeShift.opening_cash)"></strong></div>
                        <div class="flex justify-between text-emerald-700"><span>المبيعات النقدية:</span><strong x-text="fmtMoney(activeShift.cash_sales_total)"></strong></div>
                        <div class="flex justify-between text-sky-700"><span>سندات قبض نقدية:</span><strong x-text="fmtMoney(activeShift.cash_in_total)"></strong></div>
                        <div class="flex justify-between text-rose-700"><span>مصروفات وسحوبات الدرج:</span><strong x-text="fmtMoney(activeShift.cash_out_total)"></strong></div>
                        <div class="flex justify-between pt-2 border-t font-extrabold text-sm">
                            <span>النقد المتوقع بالدرج:</span>
                            <span x-text="`${fmtMoney(activeShift.expected_cash)} ${currencySymbol()}`"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold mb-1">النقد الفعلي المعدود في الدرج *</label>
                        <input type="number" step="0.01" min="0" x-model="shiftClosingActualCash" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2.5 font-bold text-base">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">ملاحظات الإغلاق</label>
                        <input type="text" x-model="shiftNotes" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-sm">
                        إغلاق الوردية وطباعة Z-Report
                    </button>
                </form>
            </template>
        </div>

        <!-- Yemeni Payment Modal (F8) -->
        <div x-show="modal === 'payment'" class="w-full max-w-2xl bg-white rounded-2xl p-5 space-y-4 shadow-2xl max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b pb-3">
                <div>
                    <h3 class="font-bold text-base">نافذة الدفع اليمنية</h3>
                    <div class="text-xs text-slate-500">إجمالي الفاتورة: <strong class="text-lg text-slate-900" x-text="`${fmtMoney(cartComputation.totalAmount)} ${currencySymbol()}`"></strong></div>
                </div>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <!-- Customer Selector & Offline Customer Creation -->
            <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 space-y-2.5">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-bold text-slate-700">
                        العميل المختار: <span class="text-emerald-700" x-text="selectedCustomer ? `${selectedCustomer.name} (${selectedCustomer.phone || '-'})` : 'عميل نقدي'"></span>
                    </span>
                    <button type="button" @click="showNewCustomerForm = !showNewCustomerForm"
                            class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-[11px] font-bold">
                        <span x-text="showNewCustomerForm ? 'إلغاء' : '+ عميل آجل جديد أوفلاين'"></span>
                    </button>
                </div>

                <div x-show="!showNewCustomerForm" class="space-y-1.5">
                    <input type="text" x-model="customerSearchQuery" placeholder="ابحث عن عميل بالاسم أو الهاتف..."
                           class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-xs bg-white">
                    <div class="flex gap-1.5 overflow-x-auto pb-1">
                        <button type="button" @click="selectedCustomer = null"
                                :class="!selectedCustomer ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border-slate-200'"
                                class="px-2.5 py-1 rounded-lg border text-[11px] shrink-0">نقدي (عام)</button>
                        <template x-for="cust in filteredPaymentCustomers" :key="`${cust.id}-${cust.client_uuid}`">
                            <button type="button" @click="selectedCustomer = cust"
                                    :class="selectedCustomer && ((selectedCustomer.client_uuid && selectedCustomer.client_uuid === cust.client_uuid) || Number(selectedCustomer.id) === Number(cust.id)) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200'"
                                    class="px-2.5 py-1 rounded-lg border text-[11px] shrink-0">
                                <span x-text="cust.name"></span>
                                <span class="opacity-75" x-text="`(${fmtMoney(cust.current_balance)})`"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Inline Offline Customer Form -->
                <div x-show="showNewCustomerForm" class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-slate-200 text-xs">
                    <input type="text" x-model="newCustomerForm.name" placeholder="اسم العميل *" class="rounded-xl border border-slate-300 px-2.5 py-1.5 bg-white">
                    <input type="text" x-model="newCustomerForm.phone" placeholder="رقم الجوال" class="rounded-xl border border-slate-300 px-2.5 py-1.5 bg-white">
                    <div class="flex gap-1.5">
                        <input type="number" min="0" x-model="newCustomerForm.credit_limit" placeholder="سقف الدين" class="w-full rounded-xl border border-slate-300 px-2.5 py-1.5 bg-white">
                        <button type="button" @click="createOfflineCustomer()" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white font-bold shrink-0">حفظ</button>
                    </div>
                </div>
            </div>

            <!-- Quick Yemeni Banknotes -->
            <div class="space-y-1.5">
                <div class="text-[11px] font-bold text-slate-500">فئات النقد اليمنية السريعة:</div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="applyBanknote(1000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 text-xs font-bold">+1,000</button>
                    <button type="button" @click="applyBanknote(5000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 text-xs font-bold">+5,000</button>
                    <button type="button" @click="applyBanknote(10000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 text-xs font-bold">+10,000</button>
                    <button type="button" @click="applyBanknote(20000)" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 text-xs font-bold">+20,000</button>
                    <button type="button" @click="setExactCash()" class="px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-bold">بالضبط</button>
                    <button type="button" @click="clearCashPayment()" class="px-3 py-1.5 rounded-xl bg-slate-200 text-slate-700 text-xs font-bold">تصفير النقد</button>
                </div>
            </div>

            <!-- Payment Rows (Cash + Yemeni Wallets) -->
            <div class="space-y-2">
                <template x-for="(row, rIdx) in paymentRows" :key="rIdx">
                    <div class="grid grid-cols-12 gap-2 items-center text-xs">
                        <select x-model="row.method" @change="row.untouchedDefault = false"
                                class="col-span-4 rounded-xl border border-slate-300 px-2.5 py-2 bg-white font-semibold">
                            <option value="cash">💵 نقدي</option>
                            <option value="kuraimi">🏦 الكريمي</option>
                            <option value="jawali">📱 جوالي</option>
                            <option value="floosak">📱 فلوسك</option>
                            <option value="onecash">📱 ون كاش</option>
                            <option value="card">💳 بطاقة</option>
                            <option value="bank_transfer">🏦 تحويل بنكي</option>
                        </select>
                        <input type="number" step="0.01" min="0" x-model="row.amount" @input="row.untouchedDefault = false"
                               placeholder="المبلغ" class="col-span-4 rounded-xl border border-slate-300 px-3 py-2 font-bold">
                        <input type="text" x-model="row.reference_number" placeholder="رقم المرجع (اختياري)"
                               class="col-span-3 rounded-xl border border-slate-300 px-2.5 py-2">
                        <button type="button" @click="removePaymentRow(rIdx)" class="col-span-1 text-rose-500 font-bold text-center">✕</button>
                    </div>
                </template>

                <div class="flex flex-wrap gap-1.5 pt-1">
                    <button type="button" @click="addPaymentRow('kuraimi')" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold">+ الكريمي</button>
                    <button type="button" @click="addPaymentRow('jawali')" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold">+ جوالي</button>
                    <button type="button" @click="addPaymentRow('floosak')" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold">+ فلوسك</button>
                    <button type="button" @click="addPaymentRow('onecash')" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-[11px] font-bold">+ ون كاش</button>
                </div>
            </div>

            <!-- Credit Amount + Change Display -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                <div class="bg-amber-50/60 border border-amber-200 rounded-xl p-3 space-y-1.5">
                    <div class="flex items-center justify-between text-xs">
                        <label class="font-bold text-amber-900">مبلغ الآجل (على الحساب)</label>
                        <button type="button" @click="setRemainingAsCredit()" class="text-[11px] text-amber-800 underline font-bold">المتبقي آجل</button>
                    </div>
                    <input type="number" step="0.01" min="0" x-model="creditAmountInput"
                           class="w-full rounded-xl border border-amber-300 px-3 py-2 font-bold text-sm bg-white">
                </div>

                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex flex-col justify-between">
                    <div class="text-xs font-bold text-emerald-800">الباقي للعميل (Change)</div>
                    <div class="text-3xl font-extrabold text-emerald-700 tracking-tight">
                        <span x-text="fmtMoney(paymentSummary.changeAmount)"></span>
                        <span class="text-sm" x-text="currencySymbol()"></span>
                    </div>
                </div>
            </div>

            <!-- Manager Override PIN if needed -->
            <div x-show="cartComputation.requiresCostOverride && !canOverrideDiscounts()"
                 class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs flex items-center justify-between gap-3">
                <span class="font-bold text-rose-800">مطلوب رمز موافقة المدير (البيع أقل من التكلفة):</span>
                <input type="password" inputmode="numeric" maxlength="4" x-model="paymentOverridePin" placeholder="4 أرقام"
                       class="w-28 text-center rounded-xl border border-rose-300 py-1.5 font-bold bg-white">
            </div>

            <div x-show="!paymentSummary.isBalanced" class="p-2.5 rounded-xl bg-rose-50 text-rose-700 text-xs font-semibold"
                 x-text="paymentSummary.errors[0] || 'تحقق من توازن المدفوع + الآجل'"></div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <div class="text-xs text-slate-600">
                    صافي المدفوع: <strong x-text="fmtMoney(paymentSummary.paidAmount)"></strong> + آجل: <strong x-text="fmtMoney(paymentSummary.creditAmount)"></strong>
                </div>
                <button type="button" @click="submitPaymentModal()" :disabled="!paymentSummary.isBalanced"
                        class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold text-sm">
                    تأكيد وإصدار الفاتورة ✓
                </button>
            </div>
        </div>

        <!-- Held Carts Modal (F4) -->
        <div x-show="modal === 'held'" class="w-full max-w-lg bg-white rounded-2xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-bold text-base">السلات المعلقة (F4)</h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <button x-show="cart.length > 0" type="button" @click="holdCurrentCart()"
                    class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs">
                ⏸️ تعليق الفاتورة الحالية (<span x-text="fmtMoney(cartComputation.totalAmount)"></span> <span x-text="currencySymbol()"></span>)
            </button>

            <div class="divide-y max-h-72 overflow-y-auto text-xs">
                <template x-for="h in heldCarts" :key="h.id">
                    <div class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900" x-text="h.customer_name || 'عميل نقدي'"></div>
                            <div class="text-slate-500" x-text="`${(h.items || []).length} أصناف • ${fmtMoney(h.total_amount)} ${currencySymbol()}`"></div>
                        </div>
                        <div class="flex gap-1.5">
                            <button type="button" @click="restoreHeldCart(h)" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-bold">استرجاع</button>
                            <button type="button" @click="deleteHeldCart(h.id)" class="px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-600 font-bold">حذف</button>
                        </div>
                    </div>
                </template>
                <div x-show="heldCarts.length === 0" class="py-6 text-center text-slate-400">لا توجد سلات معلقة.</div>
            </div>
        </div>

        <!-- Quick Drawer Movements Modal (Offline) -->
        <div x-show="modal === 'drawer'" class="w-full max-w-lg bg-white rounded-2xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-bold text-base">حركات الدرج السريعة (أوفلاين)</h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>

            <div class="grid grid-cols-4 gap-1.5 text-[11px] font-bold">
                <button type="button" @click="drawerTab = 'expense'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'expense' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="py-2 rounded-xl">مصروف نثري</button>
                <button type="button" @click="drawerTab = 'employee_advance'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'employee_advance' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="py-2 rounded-xl">سلفة عامل</button>
                <button type="button" @click="drawerTab = 'supplier_payment'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'supplier_payment' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="py-2 rounded-xl">دفعة مورد</button>
                <button type="button" @click="drawerTab = 'customer_settlement'; drawerForm.payee_id = ''; drawerForm.customer_id = ''" :class="drawerTab === 'customer_settlement' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="py-2 rounded-xl">قبض عميل</button>
            </div>

            <form @submit.prevent="submitDrawerMovement()" class="space-y-3 text-xs">
                <div x-show="drawerTab === 'expense'">
                    <label class="block font-semibold mb-1">بند المصروف</label>
                    <select x-model="drawerForm.category" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        <template x-for="cat in drawerCategories" :key="cat">
                            <option :value="cat" x-text="cat"></option>
                        </template>
                    </select>
                </div>

                <div x-show="drawerTab === 'employee_advance'">
                    <label class="block font-semibold mb-1">اختر العامل / الموظف</label>
                    <select x-model="drawerForm.payee_id" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        <option value="">— اختر العامل —</option>
                        <template x-for="emp in employeeQuickPayees" :key="emp.id">
                            <option :value="emp.id" x-text="emp.name"></option>
                        </template>
                    </select>
                </div>

                <div x-show="drawerTab === 'supplier_payment'" class="space-y-2">
                    <div>
                        <label class="block font-semibold mb-1">اختر المورد</label>
                        <select x-model="drawerForm.payee_id" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            <option value="">— اختر المورد —</option>
                            <template x-for="sup in supplierQuickPayees" :key="sup.id">
                                <option :value="sup.id" x-text="sup.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div x-show="drawerTab === 'customer_settlement'" class="space-y-2">
                    <div>
                        <label class="block font-semibold mb-1">اختر العميل</label>
                        <select x-model="drawerForm.customer_id" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            <option value="">— اختر العميل —</option>
                            <template x-for="c in customers" :key="`${c.id}-${c.client_uuid}`">
                                <option :value="c.id" x-text="`${c.name} (${fmtMoney(c.current_balance)})`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">وسيلة القبض</label>
                        <select x-model="drawerForm.method" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            <option value="cash">نقدي</option>
                            <option value="kuraimi">الكريمي</option>
                            <option value="jawali">جوالي</option>
                            <option value="floosak">فلوسك</option>
                            <option value="onecash">ون كاش</option>
                            <option value="bank_transfer">تحويل بنكي</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold mb-1">المبلغ (<span x-text="currencySymbol()"></span>) *</label>
                    <input type="number" step="0.01" min="0.01" x-model="drawerForm.amount" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold text-sm">
                </div>

                <div>
                    <label class="block font-semibold mb-1">ملاحظات</label>
                    <input type="text" x-model="drawerForm.notes" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm">
                    حفظ وإصدار سند الدرج ✓
                </button>
            </form>
        </div>

        <!-- Manager Override PIN Modal (for F2 quick checkout below cost) -->
        <div x-show="modal === 'override_pin'" class="w-full max-w-sm bg-white rounded-2xl p-5 space-y-4 shadow-xl text-center">
            <h3 class="font-bold text-base text-amber-800">موافقة المدير (بيع أقل من التكلفة)</h3>
            <p class="text-xs text-slate-600">أدخل رمز PIN الخاص بالمدير أو المالك لإتمام الفاتورة:</p>
            <div x-show="overridePinError" class="text-xs text-rose-600 font-bold" x-text="overridePinError"></div>
            <form @submit.prevent="confirmManagerOverrideModal()" class="space-y-3">
                <input type="password" inputmode="numeric" maxlength="4" x-model="overridePinInput" autofocus
                       class="w-full text-center text-2xl tracking-[0.5em] font-bold rounded-xl border border-slate-300 py-2.5">
                <div class="flex gap-2">
                    <button type="button" @click="closeModal()" class="flex-1 py-2 rounded-xl border border-slate-300 text-xs font-bold">إلغاء</button>
                    <button type="submit" class="flex-1 py-2 rounded-xl bg-amber-600 text-white text-xs font-bold">موافقة</button>
                </div>
            </form>
        </div>

        <!-- Setup PIN Modal -->
        <div x-show="modal === 'setup_pin'" class="w-full max-w-sm bg-white rounded-2xl p-5 space-y-4 shadow-xl text-center">
            <h3 class="font-bold text-base">تعيين رمز القفل السريع PIN</h3>
            <p class="text-xs text-slate-500">أدخل 4 أرقام لقفل شاشة الكاشير مؤقتاً دون مسح السلة.</p>
            <div x-show="lockError" class="text-xs text-rose-600 font-bold" x-text="lockError"></div>
            <form @submit.prevent="saveQuickPinAndLock()" class="space-y-3">
                <input type="password" inputmode="numeric" maxlength="4" x-model="lockSetupPinInput" autofocus placeholder="4 أرقام"
                       class="w-full text-center text-2xl tracking-[0.5em] font-bold rounded-xl border border-slate-300 py-2.5">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-600 text-white text-xs font-bold">حفظ الرمز وقفل الشاشة</button>
            </form>
        </div>

        <!-- Encrypted Emergency Backup Modal -->
        <div x-show="modal === 'backup'" class="w-full max-w-md bg-white rounded-2xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-bold text-base">تصدير نسخة طوارئ مشفرة (AES-256-GCM)</h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>
            <p class="text-xs text-slate-600">
                يتم تشفير كافة العمليات المعلقة في طابور المزامنة وإعدادات الجهاز محلياً عبر Web Crypto API في ملف JSON آمن.
            </p>
            <div x-show="backupStatusMsg" class="p-2.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold" x-text="backupStatusMsg"></div>
            <div class="space-y-3 text-xs">
                <div>
                    <label class="block font-semibold mb-1">كلمة مرور التشفير (اختياري — الافتراضي رمز الجهاز)</label>
                    <input type="password" x-model="backupPassphrase" placeholder="أدخل كلمة مرور قوية..."
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>
                <button type="button" @click="downloadEncryptedBackup()"
                        class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm">
                    ⬇️ تنزيل النسخة المشفرة JSON
                </button>
            </div>
        </div>

        <!-- Outbox Inspector Modal -->
        <div x-show="modal === 'outbox'" class="w-full max-w-xl bg-white rounded-2xl p-5 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 class="font-bold text-base">طابور المزامنة المحلي (<span x-text="pendingOutboxItems.length"></span>)</h3>
                <button type="button" @click="closeModal()" class="text-slate-400 hover:text-slate-700">✕</button>
            </div>
            <div class="text-xs text-slate-500">آخر مزامنة ناجحة: <strong x-text="lastSyncAt || 'لم تتم بعد'"></strong></div>
            <div class="divide-y max-h-72 overflow-y-auto text-xs">
                <template x-for="item in pendingOutboxItems" :key="item.client_uuid">
                    <div class="py-2 flex items-start justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-800" x-text="`${item.entity_type} — ${item.client_uuid.slice(0, 8)}...`"></div>
                            <div class="text-slate-500" x-text="item.created_at"></div>
                            <div x-show="item.last_error" class="text-rose-600 font-semibold mt-0.5" x-text="item.last_error"></div>
                        </div>
                        <span class="px-2 py-0.5 rounded font-bold shrink-0"
                              :class="item.status === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800'"
                              x-text="item.status"></span>
                    </div>
                </template>
                <div x-show="pendingOutboxItems.length === 0" class="py-8 text-center text-emerald-600 font-bold">
                    ✓ كافة العمليات متزامنة مع السيرفر ولا توجد سجلات معلقة.
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="syncNow(true)" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">إعادة المحاولة الآن</button>
            </div>
        </div>
    </div>
</body>
</html>
