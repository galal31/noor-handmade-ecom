<?php

require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/seo.php';

$page_title = 'الشحن والاستبدال والاسترجاع | Noor Handmade';
$page_description = 'تعرف على خطوات تجهيز وشحن طلبات Noor Handmade وكيفية التواصل عند وجود مشكلة أو طلب استبدال أو استرجاع.';
$page_canonical_path = 'shipping_returns.php';
$page_stylesheets = ['css/info-pages.css?v=1'];
$page_structured_data = [seo_breadcrumb_schema([
    ['name' => 'الرئيسية', 'url' => ''],
    ['name' => 'الشحن والاستبدال والاسترجاع', 'url' => 'shipping_returns.php'],
])];

require_once __DIR__ . '/includes/header.php';
?>

<main class="info-page">
    <div class="container info-content">
        <nav class="seo-breadcrumb" aria-label="مسار التنقل">
            <ol><li><a href="index.php">الرئيسية</a></li><li aria-current="page">الشحن والاستبدال والاسترجاع</li></ol>
        </nav>
        <header class="info-page-header">
            <h1>الشحن والاستبدال والاسترجاع</h1>
            <p>نحرص على توضيح تفاصيل الطلب والتوصيل والتعامل مع أي مشكلة قبل إتمام الإجراءات.</p>
        </header>
        <section class="info-section">
            <h2>تجهيز وشحن الطلب</h2>
            <p>يبدأ تجهيز الطلب بعد تأكيده. تختلف مدة وطريقة التوصيل حسب عنوان العميل وطبيعة القطعة، ويتم تأكيد التفاصيل المتاحة مع العميل قبل الشحن.</p>
        </section>
        <section class="info-section">
            <h2>عند استلام الطلب</h2>
            <p>يرجى مراجعة القطعة عند الاستلام والتواصل معنا في أقرب وقت إذا وصل منتج مختلف أو متضرر، مع الاحتفاظ بالقطعة وتغليفها وصور توضح المشكلة.</p>
        </section>
        <section class="info-section">
            <h2>طلبات الاستبدال أو الاسترجاع</h2>
            <p>تُراجع أهلية الطلب بحسب حالة القطعة وطبيعتها وسبب الطلب. يجب التواصل معنا أولًا قبل إعادة أي منتج للحصول على خطوات الإرجاع المناسبة.</p>
        </section>
        <section class="info-section">
            <h2>طبيعة المنتجات اليدوية</h2>
            <p>قد توجد اختلافات بسيطة بين القطع اليدوية في اللون أو التفاصيل، وهي جزء طبيعي من طبيعة الصناعة اليدوية وليست عيبًا في المنتج.</p>
        </section>
        <div class="info-contact-box">
            لتأكيد تفاصيل الشحن أو مناقشة مشكلة في الطلب: <a href="https://wa.me/201150926556">تواصل معنا عبر واتساب</a>.
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
