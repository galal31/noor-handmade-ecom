# Noor Handmade E-commerce

متجر إلكتروني مبني باستخدام PHP وMySQL لإدارة المنتجات متعددة الصور، الأقسام، السلة، المخزون، الطلبات، التتبع، وحسابات العملاء، مع لوحة تحكم للإدارة.

## المتطلبات

- PHP 8.1 أو أحدث
- MySQL أو MariaDB
- Composer
- Apache مع `mod_rewrite` وقراءة ملفات `.htaccess`

## التشغيل المحلي

1. ضع المشروع داخل مجلد الويب، مثل `D:\xampp\htdocs\noor`.
2. أنشئ قاعدة بيانات باسم `noor_handmade_db`.
3. نفّذ ملفات SQL الموجودة داخل `migrations` بالترتيب.
4. نفّذ `composer install`.
5. انسخ `includes/local_config.example.php` إلى `includes/local_config.php` وأضف إعدادات SMTP المحلية.
6. افتح `http://localhost/noor`.

ملف `includes/local_config.php` وسجلات البريد مستبعدة من Git لحماية بيانات الدخول.

## أهم الوظائف

- منتجات بصور متعددة وصورة رئيسية ومخزون.
- روابط Slugs للمنتجات والأقسام.
- سلة وCheckout آمنان مع حماية CSRF.
- إدارة دورة الطلب، الشحن، المخزون، الأرشفة، وسجل الحالات.
- تتبع الطلبات مع Rate Limiting.
- تفعيل البريد واستعادة كلمة المرور.
- حماية جلسات المستخدم والأدمن وعمليات رفع الصور.

## إعداد SEO بعد النشر

1. اضبط `APP_URL` في `includes/local_config.php` على رابط الموقع النهائي باستخدام HTTPS، بدون شرطة مائلة في النهاية.
2. أضف قيمة التحقق من Google Search Console في `GOOGLE_SITE_VERIFICATION` عند استخدام طريقة HTML tag.
3. تأكد أن الروابط التالية تعمل على الدومين النهائي:
   - `/robots.txt`
   - `/sitemap.xml`
   - `/merchant-feed.xml`
4. أرسل `/sitemap.xml` داخل Google Search Console، ثم افحص الرئيسية وصفحة منتج باستخدام URL Inspection.
5. استخدم `/merchant-feed.xml` كمصدر بيانات مجدول داخل Google Merchant Center.
6. اضبط إعدادات الشحن والاسترجاع الحقيقية داخل Merchant Center لتطابق صفحة `shipping_returns.php` وسياسة المتجر الفعلية.

ملفات Sitemap وMerchant Center يتم توليدها ديناميكيًا من الأقسام والمنتجات الموجودة في قاعدة البيانات.
