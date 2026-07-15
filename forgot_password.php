<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success = '';
$csrfToken = security_csrf_token('forgot_password_csrf');
$clientKey = security_client_key('forgot_password');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $blockedSeconds = login_is_blocked($pdo, 'password_reset', $clientKey);
    if (!security_csrf_is_valid('forgot_password_csrf', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    } elseif ($blockedSeconds > 0) {
        $errors[] = 'تم إرسال محاولات كثيرة. حاول لاحقًا.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'أدخل بريدًا إلكترونيًا صحيحًا.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND account_status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        try {
            if ($user) {
                $token = bin2hex(random_bytes(32));
                send_password_reset_email($email, $user['full_name'], $token);
                $update = $pdo->prepare('UPDATE users SET password_reset_token_hash = ?, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
                $update->execute([hash('sha256', $token), $user['id']]);
            }
            register_failed_login($pdo, 'password_reset', $clientKey);
            $success = 'إذا كان البريد مرتبطًا بحساب نشط، أرسلنا إليه رابط الاستعادة.';
        } catch (Throwable $e) {
            $errors[] = mail_failure_diagnostic('password_reset', $e);
        }
    }
}

$page_title = 'استعادة كلمة المرور | Noor Handmade';
$page_stylesheets = ['css/account-flow.css?v=2'];
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
                        <p>نساعدك ترجع لحسابك بأمان وتكمل اكتشاف القطع المصنوعة بحب.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="account-flow-content">
                        <span class="account-flow-icon"><i class="fa-solid fa-key"></i></span>
                        <span class="account-flow-kicker">أمان حسابك</span>
                        <h1 class="account-flow-title">نسيت كلمة المرور؟</h1>
                        <p class="account-flow-copy">اكتب البريد المرتبط بحسابك، وسنرسل لك رابطًا آمنًا لتعيين كلمة مرور جديدة. الرابط صالح لمدة ساعة.</p>

                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger account-flow-alert"><i class="fa-solid fa-circle-exclamation ms-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success account-flow-alert"><i class="fa-solid fa-circle-check ms-2"></i><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-4">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <div class="account-input-wrap">
                                    <i class="fa-regular fa-envelope"></i>
                                    <input id="email" name="email" type="email" class="form-control" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="name@example.com" required>
                                </div>
                            </div>
                            <div class="d-grid"><button class="btn account-flow-submit" type="submit">إرسال رابط الاستعادة <i class="fa-solid fa-arrow-left me-2"></i></button></div>
                        </form>
                        <a class="account-flow-back" href="login.php"><i class="fa-solid fa-arrow-right"></i> العودة لتسجيل الدخول</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
