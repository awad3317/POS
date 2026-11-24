# خطة التنفيذ التفصيلية الشاملة: نظام نقاط البيع الأوفلاين للسوق اليمني (Yemen POS)

> **هدف المستند:** دليل هندسي وتنفيذي شامل (Task-by-Task Execution Blueprint) لتصفية المشروع الحالي (إزالة Filament و Livewire الثقيلة مع الحفاظ على نواة Laravel 11) وتحويله في **6 أيام عمل باستخدام الـ AI** إلى نظام نقاط بيع **أوفلاين أولاً (Offline-First PWA)**، فائق السرعة والخفة، خالٍ من الأخطاء المالية، ومهيأ بالكامل للسوق اليمني (بقالات وسوبرماركت، محلات ملابس وأحذية، عطور وإكسسوارات، وتجزئة عامة) مع دورة كاملة لـ **حسابات العملاء، حسابات الموردين، وحسابات العمال والموظفين**.

---

## 1. الدستور الهندسي والمالي (قواعد غير قابلة للتنازل)

يجب الالتزام بهذه القواعد في كل سطر كود يكتبه المطور أو وكيل الـ AI خلال جميع المراحل:

### 1.1 الحزمة التقنية المعتمدة (Lightweight Tech Stack)

| الطبقة | التقنية المعتمدة | السبب العملي |
| :--- | :--- | :--- |
| **الخلفية والـ API** | `Laravel 11` (PHP 8.2+) + `Laravel Sanctum` | استثمار النواة الحالية مع مصادقة توكنات مستقلة لكل جهاز كاشير. |
| **واجهات الإدارة** | `Blade` + `Tailwind CSS v4` + `Alpine.js` | خفيفة جداً على أجهزة المحلات الضعيفة، سهلة التخصيص RTL، ويكتبها الـ AI بدقة عالية. |
| **شاشة الكاشير (`/pos`)** | `Alpine.js` + `Tailwind CSS v4` + `Dexie.js` (IndexedDB) + `sw.js` | تعمل أوفلاين 100% بسرعة استجابة `< 2ms` دون أي اعتماد على Livewire أو السيرفر أثناء البيع. |
| **الدقة المالية** | `brick/money` (أو `BCMath`) في PHP + `decimal.js` في JS | منع أخطاء الكسور العشرية (`float`) نهائياً في الفواتير والضرائب والخصومات والرواتب. |
| **الأمان والرقابة** | `spatie/laravel-permission` + `spatie/laravel-activitylog` | فصل كامل لصلاحيات الكاشير عن التكلفة والأرباح مع سجل رقابي غير قابل للمسح. |
| **الطباعة والباركود** | قوالب `HTML/CSS` محلية + `iframe.srcdoc` + `JsBarcode` + `qrcode` | طباعة حرارية فورية (`80mm/58mm` وملصقات `50x25mm`) أوفلاين في `< 200ms` بدون mPDF. |
| **التخزين والنسخ الاحتياطي** | `MySQL 8 / PostgreSQL` + `Redis` + `spatie/laravel-backup` | فهارس مركبة تبدأ بـ `store_id` ونسخ احتياطي مشفر كل ساعة. |

### 1.2 القواعد السبع الذهبية للسلامة المالية
1. **ممنوع `float` و `floatval()` في أي حساب مالي:** جميع الأعمدة المالية في قاعدة البيانات `DECIMAL(19, 4)` (تستوعب حتى مئات التريليونات بالريال اليمني دون Overflow)، وجميع الكميات `DECIMAL(12, 3)` (لدعم الكسور العشرية للأصناف الموزونة في البقالات).
2. **ممنوع `onDelete('cascade')` على أي علاقة مالية أو تاريخية:** استبدالها بـ `restrictOnDelete()` مع `SoftDeletes` على المنتجات والمستخدمين والعملاء والموردين والعمال لحماية التاريخ المالي.
3. **الفاتورة والسند المؤكدان غير قابلين للحذف أو التعديل الصامت (`Immutable Ledger`):** إلغاء أزرار حذف وتعديل الفواتير وسندات القبض/الصرف المؤكدة نهائياً؛ أي تصحيح يتم عبر **سند مرتجع أو قيد عكسي**.
4. **معاملات ذرية وأقفال تزاحم:** كل عملية بيع، مرتجع، مشتريات، سند قبض/صرف، أو سلفة عامل تتم داخل `DB::transaction()` مع `lockForUpdate()` على صفوف المخزون وأرصدة العملاء والموردين والعمال.
5. **حفظ اللقطة التاريخية الكاملة وقت البيع:** كل سطر في `order_items` يحفظ `product_name`, `barcode_snapshot`, `unit_name_snapshot`, `unit_cost` (تكلفة الشراء اللحظية لحساب الربح بدقة أبدية)، `unit_price`, `discount_amount`, `tax_rate`, `tax_amount`, `net_total`.
6. **دفتر حركات المخزون (`stock_movements`):** ممنوع تعديل كمية المخزون عشوائياً دون قيد حركة يوثق الكمية قبل وبعد والسبب والموظف والمرجع.
7. **قاعدة التوازن ومنع التكرار:** يرفض السيرفر أي فاتورة لا تحقق `paid_amount + credit_amount == total_amount`، ويفحص `client_uuid (UNIQUE)` لمنع تكرار الفاتورة أو السند عند تقطع شبكة 4G.

---

## 2. المرحلة 0 (اليوم 1 - صباحاً): تصفية وتنظيف المشروع الحالي

> **الهدف:** إزالة Filament و Livewire والمسارات الخطرة والكود المكرر من المشروع الحالي مع إبقاء نواة Laravel 11 نظيفة وجاهزة للبناء.

### التاسك 0.1 — أخذ نسخة احتياطية وتجهيز فرع العمل
- [x] إنشاء فرع جديد في Git باسم `refactor/lightweight-offline-pos` قبل حذف أي ملف.
- [x] توثيق ملف `.env.example` والاحتفاظ بإعدادات الاتصال بقاعدة البيانات والمنطقة الزمنية `Asia/Aden`.

### التاسك 0.2 — إزالة الحزم الثقيلة وغير المطلوبة
- [x] تشغيل `composer remove`:
  - `filament/filament`
  - `livewire/livewire`
  - `pxlrbt/filament-excel`
  - `mpdf/mpdf`
- [x] إزالة `print-js` من `package.json` (سنستبدلها بـ `iframe.srcdoc` المحلية الأسرع والأوفلاين 100%).
- [x] تنظيف `composer.json` من أي سكربتات مرتبطة بـ `filament:upgrade` وتشغيل `composer dump-autoload`.

### التاسك 0.3 — حذف الملفات والمجلدات القديمة والمكررة
- [x] حذف مجلد `app/Filament/` بالكامل (يشمل `Pos.php`, `ProductResource.php`, `OrderResource.php`, `CustomerResource.php`, `BarcodeGenerator.php`, `Settings.php`, `SalesOverview.php`, `EditOrder.php`).
- [x] حذف مجلد `app/Livewire/` بالكامل (يشمل المكونات الأساسية والنسخة المكررة `app/Livewire/Order/*`).
- [x] حذف `app/Providers/Filament/` وإزالة تسجيل `AdminPanelProvider` من `bootstrap/providers.php`.
- [x] حذف `app/Http/Controllers/InstallController.php` و `app/Http/Controllers/UtilityController.php`.
- [x] حذف `resources/views/filament/` و `resources/views/livewire/` وقالب الفاتورة القديم `3-invoice.blade.php` المعتمد على `mPDF` و `fonts.googleapis.com`.
- [x] حذف أي أصول منشورة لـ Filament داخل `public/js/filament/` و `public/css/filament/`.

### التاسك 0.4 — إغلاق الثغرات الأمنية ومشاكل الأداء الموروثة
- [x] تنظيف `routes/web.php`: حذف المسارات المكشوفة `/install` و `/clear` و `/print/{id}` نهائياً.
- [x] تنظيف `config/filesystems.php`: حذف قرص `public_uploads` الذي يكتب داخل `public/uploads` والاعتماد حصراً على قرص `public` (`storage/app/public`) مع تشغيل `php artisan storage:link`.
- [x] إعادة كتابة `app/Providers/SettingsServiceProvider.php` (أو تحويله لخدمة `StoreSettingsService`): إزالة استعلام `Schema::hasTable('settings')` في كل طلب، والاعتماد على `Cache::rememberForever("store_settings_{$storeId}", ...)`.
- [x] تنظيف `database/seeders/UserSeeder.php`: إزالة كلمة المرور المكشوفة `123456789` واستبدالها بقراءة من `.env` أو أمر Artisan مخصص لإنشاء أول حساب.

### التاسك 0.5 — تثبيت الحزم الجديدة النظيفة
- [x] تثبيت حزم PHP عبر Composer:
  - `brick/money` (دقة مالية مطلقة)
  - `spatie/laravel-permission` (الأدوار والصلاحيات)
  - `spatie/laravel-activitylog` (سجل الرقابة الجنائي)
  - `spatie/laravel-backup` (النسخ الاحتياطي التلقائي)
  - `pestphp/pest` و `pestphp/pest-plugin-laravel` (`--dev` للاختبارات المالية الآلية)
- [x] تثبيت حزم الواجهة عبر NPM:
  - `tailwindcss` + `@tailwindcss/vite`
  - `alpinejs` + `@alpinejs/collapse` + `@alpinejs/focus`
  - `dexie` (إدارة IndexedDB محلياً)
  - `decimal.js` (حسابات مالية دقيقة في المتصفح)
  - `jsbarcode` (توليد باركود `CODE_128` و `EAN_13` أوفلاين)
  - `qrcode` (توليد رمز QR للفواتير أوفلاين)
- [x] تنزيل خط عربي نظيف محلياً داخل `public/fonts/` (مثل `IBM Plex Sans Arabic` أو `Cairo` بصيغة `woff2`) لضمان عمل الواجهات والطباعة الحرارية أوفلاين 100% دون الحاجة لإنترنت.

**معيار إنجاز المرحلة 0:** المشروع يعمل بأمر `php artisan serve` و `npm run build` بلا أي خطأ، صفر إشارات لـ Filament أو Livewire، ومسارات `/install` و `/clear` تعيد `404`.

---

## 3. المرحلة 1 (اليوم 1 - مساءً): إعادة هندسة قاعدة البيانات بالكامل (22 جدولاً)

> **الهدف:** بناء قاعدة بيانات محكمة تدعم تعدد المتاجر (Multi-Tenancy)، تعدد الفروع والأجهزة، دقة مالية `DECIMAL(19,4)`، متغيرات الملابس، وحدات وميزان البقالات، **حسابات الموردين الكاملة، حسابات العمال والسلف والرواتب**، والعمل أوفلاين بلا تضارب.

### التاسك 1.1 — جداول الهيكلة وتعدد المتاجر والأجهزة
- [x] **جدول `stores`:**
  - `id`, `name`, `slug (UNIQUE)`, `logo_path`, `phone`, `address`, `tax_number (nullable)`.
  - `business_type` ENUM: `grocery`, `clothing`, `perfumes`, `general` (لتكييف الواجهة تلقائياً).
  - `currency_symbol` (افتراضي `ر.ي`), `currency_code` (افتراضي `YER`), `decimal_places` (افتراضي `0` أو `2` حسب اختيار التاجر).
  - `secondary_currency_code` (nullable مثل `SAR` أو `USD`), `exchange_rate DECIMAL(14,4)` (اختياري للسوق اليمني).
  - `feature_flags` JSON (تفعيل/تعطيل: المتغيرات، تعدد الوحدات، باركود الميزان، تاريخ الصلاحية، سعر الصرف، الضريبة).
  - `receipt_settings` JSON (مقاس الورق `80mm`/`58mm`، ترويسة وتذييل الفاتورة، إظهار QR، نص واتساب).
  - `subscription_status` (`active`/`trial`/`suspended`), `subscription_ends_at`, `onboarding_completed_at`, `timestamps`, `SoftDeletes`.
- [x] **جدول `branches`:**
  - `id`, `store_id (FK -> restrict)`, `name`, `phone`, `address`, `is_default`, `is_active`, `timestamps`, `SoftDeletes`.
- [x] **جدول `terminals` (أجهزة الكاشير المعتمدة):**
  - `id`, `store_id`, `branch_id`, `code` (مثل `T01`, `T02` فريد داخل المتجر), `name`, `last_invoice_seq (UNSIGNED BIGINT)`, `sanctum_token_id (nullable)`, `is_active`, `last_sync_at`, `timestamps`.
- [x] **تحديث جدول `users`:**
  - إضافة `store_id (FK -> restrict)`, `branch_id (nullable)`, `phone`, `pin_hash` (للقفل السريع بـ 4 أرقام), `is_active`, `SoftDeletes`.
  - التحقق من عدم وجود أي `onDelete('cascade')` مرتبط بالمستخدمين.

### التاسك 1.2 — جداول المنتجات، متغيرات الملابس، ووحدات البقالة
- [x] **جدول `categories` و `brands`:**
  - `id`, `store_id`, `name`, `sort_order`, `is_active`, `timestamps`, `SoftDeletes`. فهرس `(store_id, name)`.
- [x] **جدول `products`:**
  - `id`, `store_id`, `category_id (nullable)`, `brand_id (nullable)`.
  - `type` ENUM: `simple`, `variant`, `weighted`.
  - `name`, `sku (nullable)`, `barcode (nullable)` مع فهرس مركب `(store_id, barcode)`.
  - `scale_plu_code` (nullable: رمز الصنف المكون من 5 أرقام لباركود الميزان الإلكتروني في البقالات).
  - `purchase_price DECIMAL(19,4)`، `sale_price DECIMAL(19,4)`، `wholesale_price DECIMAL(19,4) (nullable)`.
  - `cost_currency` (nullable: `YER`/`SAR`/`USD`), `foreign_cost_price DECIMAL(19,4) (nullable)`, `profit_margin_percent DECIMAL(8,2) (nullable)` (لتحديث السعر عند تغير الصرف).
  - `quantity DECIMAL(12,3)` (رصيد مخبّأ يُحدّث عبر `stock_movements` فقط), `min_stock_alert DECIMAL(12,3)`.
  - `tax_rate DECIMAL(6,2) DEFAULT 0`, `image_path`, `is_quick_pick BOOLEAN DEFAULT false` (للأزرار السريعة بلا باركود مثل الخبز والماء).
  - `has_expiry BOOLEAN DEFAULT false`, `is_active BOOLEAN DEFAULT true`, `timestamps`, `SoftDeletes`.
- [x] **جدول `product_variants` (لمحلات الملابس والأحذية والعطور):**
  - `id`, `store_id`, `product_id (FK -> restrict)`, `size (nullable)`, `color (nullable)`, `attribute_label` (مثل `42 - أسود` أو `100ml`), `barcode`, `purchase_price DECIMAL(19,4)`, `sale_price DECIMAL(19,4)`, `quantity DECIMAL(12,3)`, `is_active`, `timestamps`, `SoftDeletes`. فهرس `(store_id, barcode)`.
- [x] **جدول `product_units` (للبقالات: حبة / باكت / كرتون وباركود متعدد):**
  - `id`, `store_id`, `product_id (FK -> restrict)`, `unit_name` (حبة، باكت، كرتون), `conversion_factor DECIMAL(12,3)` (مثلاً `24.000` للكرتون), `barcode`, `purchase_price DECIMAL(19,4)`, `sale_price DECIMAL(19,4)`, `is_default`, `timestamps`. فهرس `(store_id, barcode)`.
- [x] **جدول `product_batches` (اختياري لتواريخ الصلاحية):**
  - `id`, `store_id`, `product_id`, `batch_number`, `expiry_date`, `quantity DECIMAL(12,3)`, `timestamps`.

### التاسك 1.3 — جداول العملاء، الورديات، الفواتير، والمدفوعات
- [x] **جدول `customers`:**
  - `id`, `store_id`, `name`, `phone`, `whatsapp`, `address`, `opening_balance DECIMAL(19,4) DEFAULT 0`, `credit_limit DECIMAL(19,4) DEFAULT 0`, `current_balance DECIMAL(19,4) DEFAULT 0` (الرصيد المستحق على العميل)، `notes`, `timestamps`, `SoftDeletes`.
- [x] **جدول `shifts` (الورديات وإغلاق الصندوق):**
  - `id`, `store_id`, `branch_id`, `terminal_id`, `user_id (FK -> restrict)`, `client_uuid CHAR(36) UNIQUE`.
  - `opened_at`, `closed_at (nullable)`, `opening_cash DECIMAL(19,4)`.
  - `cash_sales_total DECIMAL(19,4) DEFAULT 0`, `cash_returns_total DECIMAL(19,4) DEFAULT 0`, `cash_in_total DECIMAL(19,4) DEFAULT 0` (سندات قبض عملاء نقدية)، `cash_out_total DECIMAL(19,4) DEFAULT 0` (مصروفات الدرج + دفعات الموردين من الدرج + سُلف العمال من الدرج).
  - `expected_cash DECIMAL(19,4) DEFAULT 0`, `actual_cash DECIMAL(19,4) (nullable)`, `difference DECIMAL(19,4) (nullable)`.
  - `status` ENUM: `open`, `closed`, `notes`, `timestamps`.
- [x] **جدول `orders` (الفواتير والسندات غير القابلة للحذف):**
  - `id`, `store_id`, `branch_id`, `terminal_id`, `shift_id (FK -> restrict)`, `user_id (FK -> restrict)`, `customer_id (nullable, FK -> restrict)`, `parent_order_id (nullable, FK -> orders)`.
  - `client_uuid CHAR(36) UNIQUE`, `invoice_number VARCHAR(50)` (مثل `INV-T01-2026-000145`) مع فهرس فريد `(store_id, invoice_number)`.
  - `type` ENUM: `sale`, `return`, `status` ENUM: `completed`, `partially_refunded`, `refunded`.
  - `subtotal DECIMAL(19,4)`, `item_discount_total DECIMAL(19,4)`, `order_discount_amount DECIMAL(19,4)`, `tax_total DECIMAL(19,4)`, `total_amount DECIMAL(19,4)`.
  - `paid_amount DECIMAL(19,4)`, `credit_amount DECIMAL(19,4)`, `change_amount DECIMAL(19,4)`.
  - `discount_override_by (nullable FK -> users)`, `notes`, `sold_at TIMESTAMP`, `synced_at TIMESTAMP`, `timestamps`.
  - فهارس مركبة: `(store_id, sold_at)`, `(store_id, customer_id)`, `(store_id, shift_id)`.
- [x] **جدول `order_items` (اللقطة التاريخية الكاملة):**
  - `id`, `store_id`, `order_id (FK -> restrict)`, `product_id (FK -> restrict)`, `variant_id (nullable)`, `unit_id (nullable)`.
  - `product_name`, `barcode_snapshot`, `unit_name_snapshot`, `conversion_factor DECIMAL(12,3) DEFAULT 1`.
  - `quantity DECIMAL(12,3)`, `unit_cost DECIMAL(19,4)`, `unit_price DECIMAL(19,4)`, `discount_amount DECIMAL(19,4) DEFAULT 0`, `tax_rate DECIMAL(6,2) DEFAULT 0`, `tax_amount DECIMAL(19,4) DEFAULT 0`, `net_total DECIMAL(19,4)`.
  - `return_condition` (nullable: `restock`/`damaged` للمرتجعات)، `timestamps`.
- [x] **جدول `payments` (مدفوعات الفواتير وسندات قبض العملاء):**
  - `id`, `store_id`, `branch_id`, `shift_id (nullable, FK -> restrict)`, `order_id (nullable, FK -> restrict)`, `customer_id (nullable, FK -> restrict)`, `user_id (FK -> restrict)`.
  - `client_uuid CHAR(36) UNIQUE`.
  - `payment_type` ENUM: `order_payment`, `credit_settlement`, `refund_payout`.
  - `method` ENUM: `cash`, `kuraimi`, `jawali`, `floosak`, `onecash`, `card`, `bank_transfer`.
  - `reference_number (nullable)` (رقم الحوالة/الإشعار)، `amount DECIMAL(19,4)`, `paid_at TIMESTAMP`, `notes`, `timestamps`.

### التاسك 1.4 — جداول حسابات الموردين والمشتريات (Supplier Accounts & Purchases)
- [x] **جدول `suppliers` (ملف المورد):**
  - `id`, `store_id`, `name`, `company_name (nullable)`, `phone`, `whatsapp`, `address`, `opening_balance DECIMAL(19,4) DEFAULT 0` (رصيد افتتاحي للمورد)، `current_balance DECIMAL(19,4) DEFAULT 0` (إجمالي المستحق للمورد على المحل)، `notes`, `timestamps`, `SoftDeletes`.
- [x] **جداول `purchases` و `purchase_items` (فواتير المشتريات ومرتجع المشتريات):**
  - `purchases`: `id`, `store_id`, `branch_id`, `supplier_id (nullable, FK -> restrict)`, `user_id (FK -> restrict)`, `parent_purchase_id (nullable, FK -> purchases)`, `type` ENUM (`purchase`, `purchase_return`), `reference_number`, `subtotal DECIMAL(19,4)`, `discount_amount DECIMAL(19,4)`, `total_amount DECIMAL(19,4)`, `paid_amount DECIMAL(19,4)`, `credit_amount DECIMAL(19,4)`, `payment_method`, `payment_source` (`cash_drawer`/`owner_pocket`/`bank`), `shift_id (nullable)`, `purchased_at TIMESTAMP`, `notes`, `timestamps`.
  - `purchase_items`: `id`, `purchase_id (FK -> restrict)`, `product_id (FK -> restrict)`, `variant_id (nullable)`, `unit_id (nullable)`, `quantity DECIMAL(12,3)`, `conversion_factor DECIMAL(12,3)`, `unit_cost DECIMAL(19,4)`, `net_total DECIMAL(19,4)`.
- [x] **جدول `supplier_payments` (سندات الصرف وسداد الموردين):**
  - `id`, `store_id`, `branch_id`, `supplier_id (FK -> restrict)`, `purchase_id (nullable, FK -> restrict)`, `shift_id (nullable, FK -> restrict)` (يُملأ إذا دُفع من درج الكاشير)، `user_id (FK -> restrict)`, `client_uuid CHAR(36) UNIQUE (nullable)`.
  - `method` ENUM: `cash`, `kuraimi`, `jawali`, `floosak`, `onecash`, `bank_transfer`, `payment_source` ENUM: `cash_drawer`, `owner_pocket`, `bank`.
  - `reference_number (nullable)`، `amount DECIMAL(19,4)`, `balance_before DECIMAL(19,4)`, `balance_after DECIMAL(19,4)`, `paid_at TIMESTAMP`, `notes`, `timestamps`.

### التاسك 1.5 — جداول حسابات العمال والموظفين والسلف والرواتب (Employee Accounts)
- [x] **جدول `employees` (ملف العامل — منفصل ومرتبط اختيارياً بـ `users`):**
  - *ملاحظة:* فصل `employees` عن `users` مع ربط اختياري بـ `user_id (nullable)` ضروري في اليمن لأن عمال المخزن والتوصيل والنظافة ليس لديهم حساب دخول للنظام ولكن لهم رواتب وسلف يومية!
  - `id`, `store_id`, `branch_id (nullable)`, `user_id (nullable, FK -> restrict)`, `name`, `phone`, `whatsapp`, `job_title` (كاشير، بائع، عامل مخزن، توصيل).
  - `salary_type` ENUM: `monthly` (شهري), `weekly` (أسبوعي), `daily` (يومي), `commission_only` (بالعمولة فقط).
  - `base_salary DECIMAL(19,4) DEFAULT 0`, `commission_percent DECIMAL(5,2) DEFAULT 0` (اختياري لبائعي محلات الملابس والعطور).
  - `opening_balance DECIMAL(19,4) DEFAULT 0`, `current_balance DECIMAL(19,4) DEFAULT 0` (موجب = مستحق للعامل على المحل، سالب = سُلف مستحقة على العامل للمحل).
  - `hired_at DATE (nullable)`, `is_active BOOLEAN DEFAULT true`, `notes`, `timestamps`, `SoftDeletes`.
- [x] **جدول `employee_transactions` (دفتر حساب العامل — سلف، رواتب، حوافز، خصومات):**
  - `id`, `store_id`, `branch_id (nullable)`, `employee_id (FK -> restrict)`, `shift_id (nullable, FK -> restrict)` (مربوط بالوردية إذا أخذ العامل سلفة من درج الكاشير)، `user_id (FK -> restrict)`, `client_uuid CHAR(36) UNIQUE (nullable)`.
  - `type` ENUM:
    - `salary_accrual` (استحقاق راتب شهري/أسبوعي/يومي — يزيد رصيد العامل `lehu`)
    - `bonus` (حافز أو عمولة مبيعات — يزيد رصيد العامل)
    - `advance` (سلفة أو مصروف يومي — ينقص رصيد العامل ويُسحب من الدرج أو المالك)
    - `salary_payout` (صرف صافي الراتب — ينقص رصيد العامل ويُثبت كمصروف)
    - `deduction` (خصم غياب أو تأخير أو إتلاف — ينقص رصيد العامل)
    - `shift_shortage` (ترحيل عجز إغلاق الوردية على الكاشير — ينقص رصيد العامل)
  - `payment_source` ENUM (nullable للقيود الدفترية مثل الاستحقاق والخصم): `cash_drawer`, `owner_pocket`, `kuraimi`, `jawali`, `floosak`, `bank`.
  - `amount DECIMAL(19,4)`, `signed_amount DECIMAL(19,4)` (موجب لرصيد العامل، سالب عليه), `balance_before DECIMAL(19,4)`, `balance_after DECIMAL(19,4)`.
  - `reference_type (nullable)`, `reference_id (nullable)` (مثل رقم الوردية عند ترحيل العجز), `transaction_date TIMESTAMP`, `notes`, `timestamps`.

### التاسك 1.6 — جداول المخزون والمصروفات والكتالوج العام والموديلات
- [x] **جدول `stock_movements` (دفتر حركات المخزون):**
  - `id`, `store_id`, `branch_id`, `product_id (FK -> restrict)`, `variant_id (nullable)`, `user_id (FK -> restrict)`.
  - `type` ENUM: `sale`, `purchase`, `purchase_return`, `return_restock`, `return_damaged`, `adjustment_in`, `adjustment_out`, `stocktake`.
  - `quantity_delta DECIMAL(12,3)`، `balance_before DECIMAL(12,3)`, `balance_after DECIMAL(12,3)`, `unit_cost DECIMAL(19,4)`.
  - `reference_type`, `reference_id`, `is_negative_alert BOOLEAN DEFAULT false`, `note`, `created_at`.
- [x] **جدول `expenses`:**
  - `id`, `store_id`, `branch_id`, `shift_id (nullable)`, `user_id`, `client_uuid CHAR(36) UNIQUE (nullable)`, `category` (إيجار، كهرباء، ماء، صيانة، نثريات)، `amount DECIMAL(19,4)`, `payment_source` (`cash_drawer`/`owner_pocket`/`bank`), `spent_at`, `notes`, `timestamps`.
- [x] **جدول `global_barcode_catalog`:**
  - `id`, `barcode VARCHAR(64) UNIQUE`, `name_ar`, `name_en (nullable)`, `category_hint`, `default_unit`, `image_path (nullable)`, `timestamps`.
- [x] تشغيل `php artisan vendor:publish` لجداول `spatie/laravel-permission` و `spatie/laravel-activitylog` وتنفيذ `php artisan migrate:fresh`.
- [x] إنشاء Trait `App\Traits\BelongsToStore` يطبق Global Scope تلقائي على `store_id` ويملأ `store_id` تلقائياً عند `creating`، وتحديث جميع الموديلات مع `$fillable` و `casts` دقيقة (`decimal:4` للمبالغ، `decimal:3` للكميات).

**معيار إنجاز المرحلة 1:** تنفيذ `migrate:fresh` و Seeder تجريبي لمتجرين (بقالة + محل ملابس) يتضمن موردين وعمالاً وعملاء بنجاح دون أخطاء، والتأكد من أن حذف أي منتج أو موظف أو مورد أو عامل له حركات مالية مرفوض أو محمي عبر `SoftDeletes`.

---

## 4. المرحلة 2 (اليوم 2): المحرك المالي الذري، الصلاحيات، وعقد المزامنة (Backend & Sync API)

> **الهدف:** بناء طبقة خدمات مالية لا تخطئ بريال واحد، تشمل المبيعات والمرتجعات والورديات وحسابات الموردين والعمال، مع واجهات مزامنة تدعم الانقطاع الكامل وإعادة الإرسال المتكرر.

### التاسك 2.1 — وحدة الحساب المالي الدقيقة (`MoneyMath`) وخدمة المخزون (`InventoryService`)
- [x] إنشاء `App\Support\MoneyMath` باستخدام `brick/money` (أو `BCMath` بدقة 4 خانات عشرية) لجميع الحسابات ومنع `float`.
- [x] بناء `InventoryService::applyMovement()` بـ `lockForUpdate()` لتسجيل كل حركة في `stock_movements` وتحديث الرصيد المخبّأ في `products`/`product_variants` مع علامة `is_negative_alert` عند تعارض البيع الأوفلاين، ودالة `calculateWeightedAverageCost()` للمشتريات.

### التاسك 2.2 — خدمة الفواتير الذرية (`OrderService`) والمرتجعات (`ReturnService`)
- [x] `OrderService::createSale()`:
  - فحص Idempotency عبر `client_uuid` أولاً (إرجاع `duplicate_ok` دون إعادة الخصم).
  - داخل `DB::transaction()`: منع الخصم السالب، إعادة حساب الضريبة والخصم على السيرفر، فحص سقف التكلفة أو `manager_override_pin`، فحص قاعدة التوازن `sum(payments) + credit == total`، فحص السقف الائتماني للعميل وتحديث `customers.current_balance` بـ `lockForUpdate()`، حفظ اللقطة التاريخية `unit_cost` في `order_items`، خصم المخزون بالوحدة المحولة، وتحديث الوردية.
- [x] `ReturnService::createReturn()`: التحقق من الكمية القابلة للإرجاع، إنشاء فاتورة `type = 'return'` مرتبطة بـ `parent_order_id`، إرجاع المخزون (`return_restock` أو `return_damaged`)، واسترداد النقد من الوردية أو خصمه من دين العميل.

### التاسك 2.3 — خدمة حسابات الموردين والمشتريات (`SupplierAccountService`)
- [x] `recordPurchase()` و `recordPurchaseReturn()`:
  - داخل `DB::transaction()`: إنشاء فاتورة شراء (أو مرتجع شراء للمورد)، تحديث المخزون عبر `InventoryService` وحساب متوسط التكلفة المرجح.
  - إضافة المبلغ الآجل (`credit_amount`) إلى `suppliers.current_balance` بـ `lockForUpdate()` (أو خصمه في حالة مرتجع المشتريات).
  - إذا كان الجزء المدفوع من درج الكاشير (`payment_source = 'cash_drawer'`)، يُسجل تلقائياً في `shifts.cash_out_total`.
- [x] `recordSupplierPayment()`: تسجيل سند صرف للمورد (نقدي من الدرج أو المالك أو حوالة كريمي/جوالي)، خصم المبلغ من `suppliers.current_balance` مع حفظ `balance_before` و `balance_after` و `client_uuid` لمنع التكرار.
- [x] `getSupplierStatement(Supplier $supplier, $from, $to)`: تجميع كشف حساب زمني موحد (رصيد افتتاحي + فواتير شراء + مرتجعات شراء + سندات صرف = الرصيد النهائي).

### التاسك 2.4 — خدمة حسابات العمال والسلف والرواتب (`EmployeeAccountService`)
- [x] `recordAdvanceOrPayout(Employee $employee, array $data, User $actor)`:
  - داخل `DB::transaction()` مع `lockForUpdate()` على صف العامل:
  - فحص `client_uuid` لمنع التكرار عند المزامنة أوفلاين.
  - خصم مبلغ السلفة (`advance`) أو صرف الراتب (`salary_payout`) من `employees.current_balance` مع حفظ `balance_before` و `balance_after`.
  - إذا كان المصدر `cash_drawer` (من درج الكاشير في الوردية المفتوحة) → تحديث `shifts.cash_out_total` تلقائياً حتى يتطابق جرد الصندوق!
- [x] `accrueSalaries(Store $store, string $periodLabel)` (توليد رواتب الشهر/الأسبوع بضغطة زر):
  - إضافة قيد `salary_accrual` بقيمة `base_salary` (+ عمولة المبيعات إن وُجدت) لكل عامل نشط، مع منع التكرار لنفس الفترة الزمنية.
- [x] `recordDeductionOrBonus()` و `transferShiftShortageToCashier(Shift $shift)`:
  - خيار للمدير عند إغلاق وردية بها عجز نقدي (`difference < 0`) لترحيل العجز بضغطة زر كقيد `shift_shortage` على حساب الكاشير مع ربطه بـ `shift_id`.
- [x] `getEmployeeStatement(Employee $employee, $from, $to)`: كشف حساب تفصيلي للعامل (الراتب المستحق + الحوافز - السلف اليومية - الخصومات = صافي المستحق صرفه).

### التاسك 2.5 — خدمة الورديات وسعر الصرف والصلاحيات (`ShiftService`, `ExchangeRateService`, `RBAC`)
- [x] `ShiftService`: فتح وردية، إغلاق وردية، ومعادلة الجرد الدقيقة الشاملة لكل الحركات النقدية في الدرج:
  - `expected_cash = opening_cash + cash_sales + cash_customer_collections - cash_returns - cash_drawer_expenses - cash_supplier_payouts - cash_employee_advances`.
- [x] `ExchangeRateService`: تحديث أسعار البيع بالريال اليمني للأصناف المسعرة بالدولار/السعودي بضغطة زر داخل Transaction مع توثيق في `activity_log`.
- [x] إعداد 4 أدوار (`owner`, `manager`, `storekeeper`, `cashier`) مع Policies عزل `store_id`، وحجب `purchase_price` والأرباح وحسابات الموردين ورواتب العمال عن الكاشير.

### التاسك 2.6 — واجهات المزامنة المحمية (`Sync API v1`)
- [x] `POST /api/v1/auth/terminal-login`: تسجيل دخول الكاشير وتفعيل الجهاز (`terminal_code`) بإصدار توكن Sanctum مرتبط بالجهاز.
- [x] `GET /api/v1/sync/bootstrap` و `GET /api/v1/sync/pull?since={iso_timestamp}`: جلب المنتجات والوحدات والمتغيرات والعملاء وقائمة أسماء العمال/الموردين (للأذونات السريعة فقط بدون أرصدتهم السرية أو أسعار الشراء).
- [x] `POST /api/v1/sync/push`: استقبال دفعة واحدة تحتوي على:
  - `shifts[]`، `orders[]`، `payments[]`، `expenses[]`، `employee_advances[]` (سلف من الدرج أوفلاين بموافقة/صلاحية)، `supplier_payments[]` (دفعة لمورد من الدرج أوفلاين)، و `audit_events[]`.
  - إرجاع نتيجة كل `client_uuid` (`synced` / `duplicate` / `error`) مع أرصدة المخزون المحدثة.

**معيار إنجاز المرحلة 2:** جميع الخدمات وواجهات `/api/v1/sync/*` تعمل بدقة، ومعادلة `expected_cash` في الوردية تعكس مبيعات الكاش + سلف العمال + دفعات الموردين من الدرج بالريال.

---

## 5. المرحلة 3 (اليوم 3): واجهات الإدارة الخفيفة ومعالج التهيئة (Blade + Tailwind + Alpine.js)

> **الهدف:** بناء 8 شاشات إدارة عربية RTL نظيفة وسريعة جداً، تتكيّف تلقائياً مع نوع النشاط (بقالة / ملابس / عطور) وتغطي دورة المحل اليمني بالكامل.

### التاسك 3.1 — القالب الأساسي ومعالج التهيئة في 60 ثانية (`/onboarding`)
- [x] تصميم Layout عربي RTL خفيف بـ Tailwind CSS v4 (قائمة جانبية واضحة، شريط علوي، نظام Toast عائم، وزر بارز «افتح شاشة الكاشير `/pos`»).
- [x] معالج التهيئة في 60 ثانية لأي متجر جديد: (اسم المحل والشعار والعملة ← نوع النشاط: بقالة / ملابس وأحذية / عطور وتجزئة ← مقاس الطابعة `80mm`/`58mm` وإنشاء التصنيفات الافتراضية والجهاز `T01` تلقائياً).

### التاسك 3.2 — شاشة إدارة المنتجات الذكية (`/products`)
- [x] جدول سريع بـ Pagination وبحث فوري بالاسم والباركود وفلاتر النواقص.
- [x] نموذج منتج متكيّف حسب نوع النشاط (`feature_flags`):
  - تعبئة تلقائية من `global_barcode_catalog` عند مسح باركود استهلاكي معروف.
  - للملابس: مولّد متغيرات سريع (مقاس × لون بباركود مستقل).
  - للبقالات: وحدات القياس (حبة / باكت / كرتون) + رمز الميزان `PLU` + خيار «تثبيت في أزرار البيع السريع».
- [x] استيراد/تصدير CSV مصحح (يطابق بالباركود، يفرض سعر التكلفة، ويدعم UTF-8 عربي).

### التاسك 3.3 — شاشة حسابات الموردين والمشتريات وجرد المخزون (`/suppliers`, `/purchases`, `/stocktake`)
- [x] **قائمة الموردين (`/suppliers`):** تعرض كل مورد مع إجمالي المشتريات، إجمالي المدفوع، **والرصيد المتبقي له على المحل** بلون واضح.
- [x] **سند صرف سريع للمورد:** دفع دفعة نقدي (من الدرج أو جيب المالك) أو عبر الكريمي/جوالي/فلوسك مع إدخال رقم الحوالة وتحديث الرصيد فوراً.
- [x] **كشف حساب المورد التفصيلي:** جدول زمني يجمع فواتير المشتريات الآجلة + المرتجعات + سندات الصرف مع الرصيد التراكمي وزر طباعة أو إرسال إشعار السداد عبر واتساب.
- [x] **فواتير المشتريات ومرتجع المشتريات (`/purchases`):** إدخال سريع بالباركود مع تحديث متوسط التكلفة المرجح.
- [x] **جرد المخزون وكرت الصنف (`/stocktake`):** جرد سريع بالباركود وتسجيل التوالف وعرض `Stock Card` الكامل.

### التاسك 3.4 — شاشة حسابات العمال والرواتب والسلف (`/employees`)
- [x] **لوحة العمال والموظفين (`/employees`):** قائمة بجميع عمال المحل (سواء لهم حساب دخول أو لا) تعرض الراتب الأساسي، مجموع السلف المسحوبة هذا الشهر، **والصافي المتبقي للعامل (أو المستحق عليه)**.
- [x] **نافذة «سلفة / مصروف يومي سريع»:** اختيار العامل ← المبلغ ← مصدر الصرف (درج الكاشير الحالي / جيب المالك / حوالة) ← ملاحظة ← حفظ فوري.
- [x] **زر «اعتماد وتوليد رواتب الشهر/الأسبوع»:** يضيف استحقاق الرواتب (والعمولات إن فُعّلت) لجميع العمال النشطين بضغطة زر ويحسب تلقائياً صافي ما يُسلّم لكل عامل بعد خصم سُلفه وعجز وردياته.
- [x] **صفحة كشف حساب العامل:** تعرض كل حركات العامل (استحقاق راتب، سلف يومية بالتاريخ والوقت، حوافز، خصومات، عجز وردية، وصرف راتب) مع زر طباعة حرارية/A4 وزر **إرسال كشف الراتب عبر واتساب**.

### التاسك 3.5 — شاشة العملاء ودفتر الديون (`/customers`)
- [x] قائمة العملاء مع إجمالي الديون المستحقة للمحل والسقف الائتماني، نافذة «سند قبض سريع»، كشف حساب تفصيلي، وزر تذكير بالرصيد عبر واتساب.

### التاسك 3.6 — شاشة الفواتير والمرتجعات والورديات (`/orders`, `/shifts`)
- [x] عرض الفواتير بدون أزرار حذف/تعديل، تفاصيل الفاتورة مع «إعادة طباعة» و «مرتجع/استبدال».
- [x] شاشة الورديات وتقرير `Z-Report` مع زر **«ترحيل العجز لحساب الكاشير»** في حال وجود نقص نقدي غير مبرر في الصندوق.

### التاسك 3.7 — شاشة المصروفات، التقارير، والموظفين والإعدادات (`/expenses`, `/reports`, `/settings`)
- [x] المصروفات التشغيلية (إيجار، كهرباء، نثريات).
- [x] التقارير المالية الحقيقية (صافي الربح بعد `COGS` والمصروفات والرواتب، ملخص الموقف المالي للمحل: **النقد + ديون العملاء - ديون الموردين - مستحقات العمال**، النواقص، الأصناف الراكدة، وتنبيهات المخزون السالب).
- [x] إدارة المستخدمين والأدوار وأرقام الـ PIN، إدارة أجهزة الكاشير وإيقاف أي جهاز عن بعد، وأداة تحديث سعر الصرف.

**معيار إنجاز المرحلة 3:** جميع شاشات الإدارة الثمانية تعمل بخفة (`< 150ms`)، مع فصل كامل لحسابات العملاء والموردين والعمال وربطها الآلي بدرج الوردية والتقارير.

---

## 6. المرحلة 4 (اليوم 4): شاشة الكاشير الأوفلاين فائقة السرعة (`/pos` Offline-First PWA)

> **الهدف:** بناء تجربة بيع يمنية فائقة البساطة والسرعة تعمل 100% أوفلاين على أي لابتوب أو تابلت دون توقف عند انقطاع الإنترنت.

### التاسك 4.1 — قاعدة البيانات المحلية (`public/js/pos-db.js` باستخدام Dexie.js)
- [ ] تعريف قاعدة `YemenPosDB` بالجداول والفهارس:
  - `products`: `id, barcode, scale_plu_code, category_id, name, is_quick_pick`
  - `variants`: `id, product_id, barcode`
  - `units`: `id, product_id, barcode`
  - `categories`: `id, sort_order`
  - `customers`: `id, name, phone`
  - `quick_payees`: `id, type, name` (قائمة أسماء العمال والموردين لتسجيل سلفة/دفعة من الدرج أوفلاين دون كشف الأرصدة السرية)
  - `held_carts`: `++id, created_at, customer_name`
  - `outbox`: `client_uuid, entity_type, payload, status, retry_count, created_at, last_error`
  - `meta`: `key` (`store_settings`, `terminal_code`, `last_invoice_seq`, `last_sync_at`, `active_shift`, `cashier_pin_hash`)
- [ ] تفعيل `navigator.storage.persist()` فور تسجيل دخول الجهاز لحماية `IndexedDB`.

### التاسك 4.2 — محرك المزامنة الخلفي (`public/js/pos-sync.js`)
- [ ] `bootstrapOrPull()`: تحميل أولي ثم سحب التحديثات فقط (`Delta Sync`) كل 3 دقائق أو بضغطة زر.
- [ ] `pushOutbox()`: دفع الفواتير والورديات والمصروفات وسلف العمال ودفعات الموردين من الدرج تلقائياً عند توفر الشبكة مع `Exponential Backoff` و `client_uuid` لمنع التكرار.
- [ ] حماية الإغلاق `beforeunload` عند وجود فواتير معلقة + زر **«تصدير نسخة طوارئ مشفرة JSON»**.

### التاسك 4.3 — شاشة البيع الذكية (`resources/views/pos/index.blade.php` + `pos-app.js`)
- [ ] شريط علوي يعرض حالة الاتصال وعداد المزامنة، قفل الكاشير (`PIN Lock`)، زر «حركات الدرج السريعة» (مصروف نثري / سلفة عامل / دفعة مورد من الدرج بموافقة PIN المدير إن لزم)، وزر إغلاق الوردية.
- [ ] حقل باركود بتركيز دائم (`Auto-Focus`) مع **محلل الباركود الذكي**:
  1. باركود الميزان الإلكتروني (`EAN-13` يبدأ بـ `20` أو `21` لاستخراج `PLU` والوزن العشري أو السعر).
  2. باركود الوحدات (`units.barcode` للكرتون/الباكت).
  3. باركود المتغيرات (`variants.barcode` للمقاس/اللون).
  4. باركود الصنف الأساسي (`products.barcode`).
- [ ] بحث فوري بالاسم (`< 2ms` بحد 20 نتيجة) + **شبكة أزرار البيع السريع بلا باركود** (خبز، ماء، ثلج، أكياس).
- [ ] سلة محاسبية بـ `decimal.js`: خصم صنف، خصم فاتورة إجمالي، ضريبة، حماية سقف التكلفة + `Manager Override PIN`، وتسجيل صامت عند حذف صنف من السلة.
- [ ] اختصارات الكيبورد: `F2` دفع نقدي فوري وطباعة، `F4` تعليق/استرجاع فاتورة، `F8` نافذة الدفع المتعدد، `+`/`-` للكمية.

### التاسك 4.4 — نافذة الدفع اليمنية وتوليد الفاتورة محلياً
- [ ] طرق الدفع: **نقدي** (مع أزرار فئات `1000`, `5000`, `10000`, `20000` وحساب الباقي بخط كبير)، **الكريمي**، **جوالي**، **فلوسك**، **ون كاش**، **بطاقة/تحويل**، **آجل**، و **دفع مختلط**.
- [ ] مسح العميل تلقائياً من السلة فور إتمام الفاتورة (منع تداخل الجلسات).
- [ ] توليد `UUID v7` ورقم فاتورة متسلسل محلي `INV-{TERMINAL}-{YYYY}-{SEQ}`، خصم محلي، حفظ في `outbox`، وطباعة فورية في خطوة واحدة.

**معيار إنجاز المرحلة 4:** فصل الإنترنت تماماً، إتمام 10 فواتير متنوعة + تسجيل سلفة عامل من الدرج + إغلاق الوردية أوفلاين، ثم إعادة الإنترنت ومطابقة كل الأرقام على السيرفر بدقة 100%.

---

## 7. المرحلة 5 (اليوم 5): الطباعة الحرارية الصامتة، واتساب، PWA، ولوحة المالك

> **الهدف:** إتمام تجربة المحل اليومية بالطباعة الحرارية الأوفلاين، ملصقات الباركود، واتساب، ولوحة جوال سريعة لصاحب المحل.

### التاسك 5.1 — الطباعة الحرارية المحلية أوفلاين (`public/js/pos-print.js`)
- [ ] قالب فاتورة حرارية عربي RTL (`80mm` و `58mm`) عبر `iframe.srcdoc` بالخط المحلي المدمج خلال `< 200ms`، مع **رمز QR Code** لرقم الفاتورة لتسريع الاسترجاع بالماسح.
- [ ] طباعة تقرير إغلاق الوردية (`Z-Report`) وسند سلفة العامل وسند صرف المورد حرارياً أوفلاين.
- [ ] توثيق ضبط `--kiosk-printing` للطباعة الصامتة الفورية + خيار `WebUSB ESC/POS` لفتح درج النقدية.

### التاسك 5.2 — طباعة استيكرات الباركود الحرارية (`/barcodes`)
- [ ] دعم مقاسات رول الملابس والعطور (`50x25mm`, `40x30mm`, `38x25mm`) وورق `A4` للبقالات عبر `JsBarcode` + `iframe.srcdoc` مع بحث AJAX بحد 20 نتيجة.

### التاسك 5.3 — تكامل واتساب بنقرة واحدة (`WhatsApp 1-Click`)
- [ ] إرسال الفاتورة للعميل، تذكير بالرصيد الآجل للعميل، إشعار سداد للمورد، وكشف الراتب والسلف للعامل عبر روابط `https://wa.me/{phone}?text=...` منسقة بالعربية.

### التاسك 5.4 — تجهيز PWA الكامل (`manifest.json` + `sw.js`) ولوحة المالك (`/owner`)
- [ ] `public/manifest.json` و `public/sw.js` بسياسة `Cache-First` للأصول والخطوط و `Stale-While-Revalidate` لـ `/pos` و `Network-Only` لـ `/api/v1/sync/*`.
- [ ] شاشة متابعة جوال لصاحب المحل (`/owner`): مبيعات اليوم، النقد الحالي في كل درج، ملخص الموقف المالي (لنا عند العملاء / علينا للموردين / سلف العمال هذا الشهر)، النواقص، وتنبيهات الخصم وحذف أصناف السلة.

**معيار إنجاز المرحلة 5:** تشغيل النظام كتطبيق مثبت (PWA) بدون إنترنت، بيع وطباعة فاتورة حرارية بـ QR Code، ومتابعة المبيعات والدرج والسلف من الجوال.

---

## 8. المرحلة 6 (اليوم 6): الاختبارات المالية الآلية، التجهيز للإنتاج، والتجربة الميدانية

> **الهدف:** ضمان دقة مالية 100% وأمان كامل قبل تنصيب النظام في أول بقالة ومحل ملابس في اليمن.

### التاسك 6.1 — حزمة اختبارات `Pest PHP` المالية والأمنية
- [ ] **اختبار التوازن والكسور (`FinancialIntegrityTest`):** فاتورة بقيمة `12,500,000 ر.ي` + خصم + ضريبة + دفع مختلط → فارق `0.0000`؛ رفض الخصم السالب وعدم التوازن.
- [ ] **اختبار منع التكرار (`SyncIdempotencyTest`):** إرسال نفس `client_uuid` 5 مرات للفواتير وسندات القبض وسلف العمال → حفظ واحد وخصم واحد فقط.
- [ ] **اختبار التزاحم وتعارض المخزون (`ConcurrencyAndOfflineConflictTest`):** بيع آخر قطعة أوفلاين من جهازين مع فاتورة شراء على السيرفر → مطابقة الرصيد مع جمع `stock_movements`.
- [ ] **اختبار حسابات الموردين والعمال والوردية (`AccountsAndShiftReconciliationTest`):**
  - فاتورة شراء آجل للمورد + سند صرف من الدرج + مرتجع شراء → تطابق `suppliers.current_balance` مع كشف الحساب.
  - استحقاق راتب عامل + سلفة يومية من درج الكاشير + ترحيل عجز وردية → تطابق `employees.current_balance` وتطابق `shifts.expected_cash` بالريال.
- [ ] **اختبار العزل والصلاحيات (`TenantAndRbacSecurityTest`):** منع الوصول بين متجرين، وحجب `purchase_price` والأرباح ورواتب العمال عن الكاشير.

### التاسك 6.2 — اختبار التحمل الأوفلاين والتجهيز للإنتاج
- [ ] **Offline Stress Test:** فصل الشبكة تماماً، إصدار 50 فاتورة + سلف عمال + دفعة مورد من الدرج، إغلاق المتصفح وفتحه، إعادة الشبكة ومطابقة السيرفر 100%.
- [ ] تفعيل `HTTPS`، كاش Laravel (`config/route/view:cache`)، `Redis`، والنسخ الاحتياطي المشفر كل ساعة عبر `spatie/laravel-backup` مع تجربة استعادة فعلية.
- [ ] تنصيب تجريبي (Pilot) لمدة أسبوع في **بقالة واحدة** و **محل ملابس واحد** لمراقبة السرعة والطباعة الحرارية قبل التعميم التجاري.

---

## 9. خارطة ما بعد الإطلاق (الإصدار V1.1 — الأسبوع الثاني وما بعده)

1. **لوحة مالك المنصة (Super Admin SaaS Panel):** إدارة اشتراكات المتاجر والباقات والتفعيل التلقائي.
2. **توسيع كتالوج الباركود اليمني (`Global Barcode Catalog`):** إثراء الجدول المركزي تلقائياً من الأصناف المراجعة.
3. **الربط الآلي لواتساب (WhatsApp Automation):** إرسال الفواتير وكشوف الديون وتقرير إغلاق الوردية اليومي لصاحب المحل تلقائياً كباقة إضافية مدفوعة.
4. **وضع الشبكة المحلية (Local LAN Box):** تشغيل محلي عبر Docker / FrankenPHP للسوبرماركتات ذات 3+ كاشيرات لمزامنة داخلية عبر Wi-Fi المحل عند انقطاع الإنترنت الخارجي لأيام.
5. **الفوترة الإلكترونية المتقدمة (`ZATCA TLV Base64 QR`):** للتوسع الإقليمي لاحقاً.