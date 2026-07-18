<?php

require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/seo.php';

$page_title = 'سياسة الخصوصية | Noor Handmade';
$page_description = 'تعرف على كيفية جمع واستخدام وحماية بياناتك عند استخدام متجر Noor Handmade وإتمام الطلبات.';
$page_canonical_path = 'privacy.php';
$page_stylesheets = ['css/info-pages.css?v=1'];
$page_structured_data = [seo_breadcrumb_schema([
    ['name' => 'الرئيسية', 'url' => ''],
    ['name' => 'سياسة الخصوصية', 'url' => 'privacy.php'],
])];

require_once __DIR__ . '/includes/header.php';
?>

<main class="info-page">
    <div class="container info-content">
        <nav class="seo-breadcrumb" aria-label="مسار التنقل">
            <ol><li><a href="index.php">الرئيسية</a></li><li aria-current="page">سياسة الخصوصية</li></ol>
        </nav>
        <header class="info-page-header">
            <h1>سياسة الخصوصية</h1>
            <p>نوضح هنا البيانات التي يحتاجها المتجر لتشغيل الحسابات وتنفيذ الطلبات وكيف نتعامل معها.</p>
        </header>
        <section class="info-section">
            <h2>البيانات التي نجمعها</h2>
            <p>قد نجمع الاسم والبريد الإلكتروني ورقم الهاتف وعنوان التوصيل وبيانات الطلب، بالإضافة إلى معلومات الجلسة اللازمة لتسجيل الدخول وتشغيل سلة المشتريات.</p>
        </section>
        <section class="info-section">
            <h2>كيف نستخدم البيانات؟</h2>
            <ul>
                <li>إنشاء الحساب وتأمينه وإرسال رسائل التفعيل والاستعادة.</li>
                <li>تنفيذ الطلب والتواصل بشأن حالته والتوصيل.</li>
                <li>تشغيل المتجر وتحسين موثوقيته ومنع إساءة الاستخدام.</li>
            </ul>
        </section>
        <section class="info-section">
            <h2>مشاركة البيانات والاحتفاظ بها</h2>
            <p>لا نبيع بيانات العملاء. لا تتم مشاركة البيانات إلا بالقدر اللازم لتشغيل الخدمة أو تنفيذ الطلب، ويتم الاحتفاظ بها بالمدة اللازمة لتقديم الخدمة والوفاء بالالتزامات المرتبطة بالطلبات.</p>
        </section>
        <section class="info-section">
            <h2>التحكم في بياناتك</h2>
            <p>يمكنك التواصل معنا للاستفسار عن بيانات حسابك أو طلب تصحيحها. قد نحتاج إلى التحقق من هوية صاحب الحساب قبل تنفيذ الطلب.</p>
        </section>
        <div class="info-contact-box">
            للاستفسارات المتعلقة بالخصوصية: <a href="https://wa.me/201150926556">01150926556</a>.
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
