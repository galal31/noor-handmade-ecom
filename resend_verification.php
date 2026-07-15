<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success = '';
$csrfToken = security_csrf_token('resend_verification_csrf');
$clientKey = security_client_key('resend_verification');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $blockedSeconds = login_is_blocked($pdo, 'verification_resend', $clientKey);
    if (!security_csrf_is_valid('resend_verification_csrf', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    } elseif ($blockedSeconds > 0) {
        $errors[] = 'تم إرسال محاولات كثيرة. حاول لاحقًا.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'أدخل بريدًا إلكترونيًا صحيحًا.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND account_status = 'pending_verification' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        try {
            if ($user) {
                $token = bin2hex(random_bytes(50));
                send_verification_email($email, $user['full_name'], $token);
                $update = $pdo->prepare('UPDATE users SET verification_token = ?, verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?');
                $update->execute([$token, $user['id']]);
            }
            register_failed_login($pdo, 'verification_resend', $clientKey);
            $success = 'إذا كان البريد مسجلًا وينتظر التفعيل، أرسلنا إليه رابطًا جديدًا.';
        } catch (Throwable $e) {
            $errors[] = mail_failure_diagnostic('resend_verification', $e);
        }
    }
}

$page_title = 'إعادة إرسال رابط التفعيل | Noor Handmade';
$page_stylesheets = ['css/account-flow.css?v=1'];
require_once __DIR__ . '/includes/header.php';
?>
<main class="account-flow-page">
    <div class="container">
        <section class="account-flow-shell">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="account-flow-brand">
                        <a href="index.php"><img src="images/logo.jpeg" alt="شعار Noor Handmade"></a>
                        <h2>Noor Handmade</h2>
                        <p>خطوة صغيرة تفصلك عن تفعيل حسابك والدخول لعالم المنتجات اليدوية المميزة.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="account-flow-content">
                        <span class="account-flow-icon"><i class="fa-solid fa-envelope-circle-check"></i></span>
                        <span class="account-flow-kicker">تفعيل الحساب</span>
                        <h1 class="account-flow-title">لم يصلك رابط التفعيل؟</h1>
                        <p class="account-flow-copy">أدخل بريدك وسنرسل رابطًا جديدًا صالحًا لمدة 24 ساعة. تأكد أيضًا من مجلد الرسائل غير المرغوب فيها.</p>

                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger account-flow-alert"><i class="fa-solid fa-circle-exclamation ms-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success account-flow-alert"><i class="fa-solid fa-circle-check ms-2"></i><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-4">
                                <label class="form-label" for="email">البريد الإلكتروني</label>
                                <div class="account-input-wrap">
                                    <i class="fa-regular fa-envelope"></i>
                                    <input class="form-control" type="email" id="email" name="email" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="name@example.com" required>
                                </div>
                            </div>
                            <div class="d-grid"><button class="btn account-flow-submit" type="submit">إرسال رابط جديد <i class="fa-solid fa-paper-plane me-2"></i></button></div>
                        </form>
                        <a class="account-flow-back" href="login.php"><i class="fa-solid fa-arrow-right"></i> العودة لتسجيل الدخول</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
