<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/db_connection.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenHash = $token !== '' ? hash('sha256', $token) : '';
$csrfToken = security_csrf_token('reset_password_csrf');
$errors = [];
$success = '';

$stmt = $pdo->prepare("SELECT id FROM users WHERE password_reset_token_hash = ? AND password_reset_expires_at >= NOW() AND account_status = 'active' LIMIT 1");
$stmt->execute([$tokenHash]);
$resetUserId = (int) $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if (!security_csrf_is_valid('reset_password_csrf', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    } elseif (!$resetUserId) {
        $errors[] = 'الرابط غير صالح أو انتهت صلاحيته.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
    } elseif ($password !== $confirm) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    } else {
        $update = $pdo->prepare('UPDATE users SET password = ?, password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ? AND password_reset_token_hash = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $resetUserId, $tokenHash]);
        $success = 'تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.';
        $resetUserId = 0;
    }
}

$page_title = 'تعيين كلمة مرور جديدة | Noor Handmade';
$page_stylesheets = ['css/account-flow.css?v=2'];
require_once __DIR__ . '/includes/header.php';
?>
<main class="account-flow-page">
    <div class="container">
        <section class="account-flow-shell">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="account-flow-brand">
                        <a href="index.php"><img src="images/logo.jpeg" alt="شعار Noor Handmade" width="118" height="118" decoding="async"></a>
                        <h2>Noor Handmade</h2>
                        <p>اختر كلمة مرور قوية وسهلة التذكر، ولا تشاركها مع أي شخص.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="account-flow-content">
                        <span class="account-flow-icon"><i class="fa-solid fa-shield-halved"></i></span>
                        <span class="account-flow-kicker">حماية الحساب</span>
                        <h1 class="account-flow-title">تعيين كلمة مرور جديدة</h1>
                        <p class="account-flow-copy">استخدم 8 أحرف على الأقل، ويفضل الجمع بين الحروف والأرقام والرموز.</p>

                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger account-flow-alert"><i class="fa-solid fa-circle-exclamation ms-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success account-flow-alert"><i class="fa-solid fa-circle-check ms-2"></i><?= htmlspecialchars($success) ?></div>
                            <div class="d-grid"><a class="btn account-flow-submit d-flex align-items-center justify-content-center" href="login.php">تسجيل الدخول الآن</a></div>
                        <?php elseif ($resetUserId): ?>
                            <form method="post" id="reset-password-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="password">كلمة المرور الجديدة</label>
                                    <div class="account-input-wrap">
                                        <i class="fa-solid fa-lock"></i>
                                        <input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                                        <button class="account-password-toggle" type="button" data-password-toggle="password" aria-label="إظهار كلمة المرور"><i class="fa-regular fa-eye"></i></button>
                                    </div>
                                    <div class="password-hint"><i class="fa-solid fa-circle-info"></i> 8 أحرف على الأقل</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="password_confirm">تأكيد كلمة المرور</label>
                                    <div class="account-input-wrap">
                                        <i class="fa-solid fa-lock"></i>
                                        <input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required>
                                        <button class="account-password-toggle" type="button" data-password-toggle="password_confirm" aria-label="إظهار كلمة المرور"><i class="fa-regular fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="d-grid"><button class="btn account-flow-submit" type="submit">حفظ كلمة المرور <i class="fa-solid fa-check me-2"></i></button></div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger account-flow-alert"><i class="fa-solid fa-link-slash ms-2"></i>الرابط غير صالح أو انتهت صلاحيته.</div>
                            <div class="d-grid"><a class="btn account-flow-submit d-flex align-items-center justify-content-center" href="forgot_password.php">طلب رابط جديد</a></div>
                        <?php endif; ?>
                        <a class="account-flow-back" href="login.php"><i class="fa-solid fa-arrow-right"></i> العودة لتسجيل الدخول</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-label', reveal ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
        button.querySelector('i').className = reveal ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
