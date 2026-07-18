<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/db_connection.php';

$message_title = 'خطأ في التفعيل';
$message_body = 'الرابط غير صالح أو انتهت صلاحيته.';
$message_icon = 'error';

$token = trim((string) ($_GET['token'] ?? ''));
if ($token !== '') {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND account_status = 'pending_verification' AND verification_expires_at >= NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'active', verification_token = NULL, verification_expires_at = NULL WHERE id = ? AND account_status = 'pending_verification'");
        if ($stmt->execute([$user['id']])) {
            $message_title = 'تم تفعيل حسابك بنجاح!';
            $message_body = 'أهلًا بك في Noor Handmade. سيتم توجيهك لتسجيل الدخول خلال لحظات.';
            $message_icon = 'success';
            header('Refresh: 5; url=login.php');
        }
    } else {
        $message_body = 'هذا الرابط غير صالح، منتهي الصلاحية، أو تم استخدامه مسبقًا.';
    }
}

$page_title = 'تفعيل الحساب | Noor Handmade';
$page_stylesheets = ['css/account-flow.css?v=2'];
require_once __DIR__ . '/includes/header.php';
$isSuccess = $message_icon === 'success';
?>
<main class="account-flow-page">
    <div class="container">
        <section class="account-flow-shell">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="account-flow-brand">
                        <a href="index.php"><img src="images/logo.jpeg" alt="شعار Noor Handmade" width="118" height="118" decoding="async"></a>
                        <h2>Noor Handmade</h2>
                        <p><?= $isSuccess ? 'حسابك أصبح جاهزًا، وننتظرك لاكتشاف منتجاتنا المصنوعة بحب.' : 'سنساعدك في الحصول على رابط تفعيل جديد والعودة لحسابك.' ?></p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="account-flow-content text-center">
                        <span class="account-flow-icon <?= $isSuccess ? 'account-flow-icon-success' : 'account-flow-icon-error' ?>">
                            <i class="fa-solid <?= $isSuccess ? 'fa-check' : 'fa-xmark' ?>"></i>
                        </span>
                        <span class="account-flow-kicker"><?= $isSuccess ? 'تم التفعيل' : 'تعذر التفعيل' ?></span>
                        <h1 class="account-flow-title"><?= htmlspecialchars($message_title) ?></h1>
                        <p class="account-flow-copy"><?= htmlspecialchars($message_body) ?></p>
                        <?php if ($isSuccess): ?>
                            <div class="alert alert-success account-flow-alert"><i class="fa-solid fa-circle-check ms-2"></i>سيتم تحويلك تلقائيًا إلى صفحة الدخول.</div>
                            <div class="d-grid"><a class="btn account-flow-submit d-flex align-items-center justify-content-center" href="login.php">تسجيل الدخول الآن</a></div>
                        <?php else: ?>
                            <div class="d-grid"><a class="btn account-flow-submit d-flex align-items-center justify-content-center" href="resend_verification.php">إرسال رابط تفعيل جديد</a></div>
                            <a class="account-flow-back justify-content-center" href="login.php"><i class="fa-solid fa-arrow-right"></i> العودة لتسجيل الدخول</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
