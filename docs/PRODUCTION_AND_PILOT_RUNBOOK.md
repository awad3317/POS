# دليل التجهيز للإنتاج والتنصيب التجريبي (Yemen POS Production & Pilot Runbook)

## 1. متطلبات السيرفر والبيئة (HTTPS + PHP + DB + Redis)

> **تنبيه أمني إلزامي:** تشغيل **Service Worker (`/sw.js`)** و **Web Crypto API (AES-GCM)** و **WebUSB** في المتصفح يتطلب وجود شهادة **HTTPS** صالحة (أو `localhost` أثناء التطوير).

### متغيرات `.env` للإنتاج
```env
APP_NAME="Yemen POS"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Aden
APP_URL=https://pos.example.ye
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yemen_pos
DB_USERNAME=yemen_pos_user
DB_PASSWORD=STRONG_DB_PASSWORD

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=STRONG_REDIS_PASSWORD
REDIS_PORT=6379

BACKUP_NAME="YemenPOS"
BACKUP_DISKS=local
BACKUP_ARCHIVE_PASSWORD="STRONG_AES256_BACKUP_PASSWORD"
```

---

## 2. أوامر النشر والكاش الإنتاجي

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link

# إنشاء المدير الأول إن لزم
php artisan yemen-pos:create-admin

# تفعيل كاش الإنتاج بالكامل
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

عند كل تحديث جديد للكود، أعد تشغيل:
```bash
php artisan optimize:clear
php artisan migrate --force
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
```

---

## 3. النسخ الاحتياطي المشفر كل ساعة (`spatie/laravel-backup`) وتجربة الاستعادة

تم ضبط `app/Console/Kernel.php` و`config/backup.php` على:
- **`backup:run --only-db --disable-notifications`**: يعمل **كل ساعة** (`hourly`) مع تشفير ZIP (`ZipArchive::EM_AES_256`) عبر `BACKUP_ARCHIVE_PASSWORD` وخيار `useSingleTransaction => true` لعدم قفل الجداول أثناء البيع.
- **`backup:clean --disable-notifications`**: يعمل يومياً الساعة `03:30` صباحاً.
- **`backup:monitor`**: يعمل يومياً الساعة `04:00` صباحاً.

### تفعيل Cron على السيرفر (Linux)
```cron
* * * * * cd /var/www/yemen-pos && php artisan schedule:run >> /dev/null 2>&1
```

### اختبار النسخ والاستعادة الفعلية
```bash
# 1. تشغيل نسخة احتياطية فورية مشفرة
php artisan backup:run --only-db

# 2. التحقق من القائمة والحالة
php artisan backup:list

# 3. اختبار الاستعادة على قاعدة بيانات تجريبية
unzip -P "$BACKUP_ARCHIVE_PASSWORD" storage/app/YemenPOS/<latest-backup>.zip -d /tmp/yemen_restore_test
mysql -u root -p yemen_pos_restore_test < /tmp/yemen_restore_test/db-dumps/*.sql
```

---

## 4. ضبط أجهزة الكاشير والطابعات الحرارية في المحلات

1. افتح Google Chrome أو Microsoft Edge على جهاز الكاشير وأنشئ اختصاراً على سطح المكتب بالهدف التالي لتفعيل **الطباعة الحرارية الصامتة**:
   ```text
   "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk-printing https://pos.example.ye/pos
   ```
2. عيّن الطابعة الحرارية (`80mm` أو `58mm`) كطابعة افتراضية في نظام التشغيل.
3. ادخل إلى `/pos` وأتمم خطوة **تفعيل الجهاز (`T01` / `T02`)** ليتم سحب البيانات وحفظها في `IndexedDB` وتفعيل `navigator.storage.persist()` وService Worker `/sw.js`.
4. للتحقق من العمل أوفلاين: افصل الإنترنت، أصدر فواتير تجريبية وحركات درج، أغلق المتصفح وأعد فتحه، ثم أعد الإنترنت وتأكد من تصفير عداد `طابور المزامنة` وتطابق الأرصدة في `/owner` و`/shifts`.

---

## 5. خطة التنصيب التجريبي الميداني (Pilot — أسبوع واحد)

| المحل التجريبي | نطاق المراقبة اليومي |
| :--- | :--- |
| **1. بقالة / سوبرماركت** | اختبار باركود الميزان (`20`/`21`)، تعدد الوحدات (`حبة`/`باكت`/`كرتون`)، أزرار البيع السريع (`is_quick_pick`)، والعمل أثناء انقطاع الإنترنت/الكهرباء. |
| **2. محل ملابس وأحذية** | اختبار متغيرات المقاس واللون، طباعة استيكرات الباركود (`/barcodes` مقاس `50x25mm`)، وتحديث سعر الصرف وإعادة التسعير (`/settings`). |
| **المراجعة الختامية للأسبوع** | مطابقة تقارير `Z-Report` مع النقد الفعلي، فحص تنبيهات `/owner` (النواقص، المخزون السالب، تجاوز التكلفة، حذف السلة)، وتأكيد نجاح 168 نسخة احتياطية ساعية مشفرة. |
