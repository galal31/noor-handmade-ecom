<?php

require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/seo.php';

$page_title = 'من نحن | Noor Handmade';
$page_description = 'تعرف على Noor Handmade ورؤيتنا في اختيار منتجات يدوية مميزة تحمل طابعًا خاصًا واهتمامًا بالتفاصيل.';
$page_canonical_path = 'about.php';
$page_stylesheets = ['css/info-pages.css?v=1'];
$page_structured_data = [seo_breadcrumb_schema([
    ['name' => 'الرئيسية', 'url' => ''],
    ['name' => 'من نحن', 'url' => 'about.php'],
])];

require_once __DIR__ . '/includes/header.php';
?>

<main class="info-page">
    <div class="container info-content">
        <nav class="seo-breadcrumb" aria-label="مسار التنقل">
            <ol><li><a href="index.php">الرئيسية</a></li><li aria-current="page">من نحن</li></ol>
        </nav>
        <header class="info-page-header">
            <h1>تفاصيل صغيرة تصنع فرقًا</h1>
            <p>في Noor Handmade نختار القطع اليدوية التي تجمع بين الشخصية والجمال والاهتمام بالتفاصيل.</p>
        </header>
        <section class="info-section">
            <h2>قصتنا</h2>
            <p>بدأت Noor Handmade من تقديرنا للمنتجات التي تحمل أثر صانعها. كل قطعة يدوية لها اختلافها وطابعها، وهذا ما يجعلها أقرب وأكثر خصوصية من المنتجات المتكررة.</p>
        </section>
        <section class="info-section">
            <h2>ما الذي نهتم به؟</h2>
            <ul>
                <li>اختيار قطع مصنوعة بعناية وخامات مناسبة.</li>
                <li>عرض صور وتفاصيل واضحة تساعدك على الاختيار.</li>
                <li>توفير تجربة طلب ومتابعة بسيطة وواضحة.</li>
            </ul>
        </section>
        <div class="info-contact-box">
            تريد معرفة المزيد أو الاستفسار عن منتج؟ <a href="https://wa.me/201150926556">تواصل معنا عبر واتساب</a>.
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
