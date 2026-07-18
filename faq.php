<?php

require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/seo.php';

$page_title = 'الأسئلة الشائعة | Noor Handmade';
$page_description = 'إجابات عن أكثر الأسئلة شيوعًا حول الطلب والدفع والتوصيل وتتبع طلبات Noor Handmade.';
$page_canonical_path = 'faq.php';
$page_stylesheets = ['css/info-pages.css?v=1'];
$page_structured_data = [seo_breadcrumb_schema([
    ['name' => 'الرئيسية', 'url' => ''],
    ['name' => 'الأسئلة الشائعة', 'url' => 'faq.php'],
])];

require_once __DIR__ . '/includes/header.php';
?>

<main class="info-page">
    <div class="container info-content">
        <nav class="seo-breadcrumb" aria-label="مسار التنقل">
            <ol><li><a href="index.php">الرئيسية</a></li><li aria-current="page">الأسئلة الشائعة</li></ol>
        </nav>
        <header class="info-page-header">
            <h1>الأسئلة الشائعة</h1>
            <p>إجابات سريعة تساعدك قبل الطلب وبعده.</p>
        </header>
        <div class="faq-list">
            <details>
                <summary>كيف أطلب منتجًا؟</summary>
                <p>اختر المنتج والكمية، أضفه إلى السلة، ثم راجع السلة وأكمل بيانات الطلب من صفحة إتمام الطلب.</p>
            </details>
            <details>
                <summary>كيف أعرف حالة طلبي؟</summary>
                <p>استخدم كود التتبع الذي يظهر بعد إنشاء الطلب داخل صفحة تتبع الطلب.</p>
            </details>
            <details>
                <summary>ماذا أفعل إذا لم تصل رسالة تفعيل الحساب؟</summary>
                <p>يمكنك استخدام صفحة إعادة إرسال رابط التفعيل والتأكد من كتابة البريد الإلكتروني الصحيح.</p>
            </details>
            <details>
                <summary>هل القطعة ستكون مطابقة للصورة تمامًا؟</summary>
                <p>نحاول عرض المنتج بأكبر قدر من الدقة، وقد تظهر اختلافات بسيطة طبيعية لأن المنتجات يدوية ولأن عرض الألوان يختلف بين الشاشات.</p>
            </details>
            <details>
                <summary>كيف أتواصل بخصوص الشحن أو مشكلة في الطلب؟</summary>
                <p>يمكنك التواصل معنا عبر واتساب على رقم 01150926556 مع ذكر كود الطلب وتفاصيل الاستفسار.</p>
            </details>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
