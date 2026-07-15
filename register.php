<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success_message = '';
$register_csrf_token = security_csrf_token('register_csrf_token');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $password_confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!security_csrf_is_valid('register_csrf_token', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    }
    if ($full_name === '' || $email === '' || $password === '') {
        $errors[] = 'جميع الحقول مطلوبة.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'البريد الإلكتروني غير صحيح.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'كلمتا المرور غير متطابقتين.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'هذا البريد الإلكتروني مسجل بالفعل.';
        }
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(50));
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, verification_token, verification_expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
        if ($stmt->execute([$full_name, $email, $password_hash, $token])) {
            $newUserId = (int) $pdo->lastInsertId();
            try {
                send_verification_email($email, $full_name, $token);
                $success_message = 'تم إنشاء الحساب. أرسلنا لك رابط تفعيل صالحًا لمدة 24 ساعة.';
            } catch (Throwable $e) {
                $pdo->prepare('DELETE FROM users WHERE id = ? AND account_status = ?')->execute([$newUserId, 'pending_verification']);
                $errors[] = mail_failure_diagnostic('registration_verification', $e) . ' لم يتم إنشاء الحساب ويمكنك المحاولة مرة أخرى.';
            }
        }
    }
}

$page_title = 'حساب جديد | Noor Handmade';
$page_stylesheets = ['css/account-flow.css?v=2'];
require_once __DIR__ . '/includes/header.php';
?>
<main class="account-flow-page">
    <div class="container">
        <section class="account-flow-shell account-flow-shell-wide">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="account-flow-brand">
                        <a href="index.php"><img src="images/logo.jpeg" alt="شعار Noor Handmade"></a>
                        <h2>Noor Handmade</h2>
                        <p>انضم لعالم القطع اليدوية الفريدة، واحتفظ بطلباتك وتابعها بسهولة.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="account-flow-content account-flow-content-compact">
                        <span class="account-flow-icon"><i class="fa-solid fa-user-plus"></i></span>
                        <span class="account-flow-kicker">حساب جديد</span>
                        <h1 class="account-flow-title">أنشئ حسابك</h1>
                        <p class="account-flow-copy">املأ بياناتك، ثم فعّل حسابك من الرسالة التي ستصلك على بريدك.</p>

                        <?php foreach ($errors as $error): ?>
                            <div class="alert alert-danger account-flow-alert"><i class="fa-solid fa-circle-exclamation ms-2"></i><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success account-flow-alert"><i class="fa-solid fa-circle-check ms-2"></i><?= htmlspecialchars($success_message) ?></div>
                            <div class="d-grid"><a class="btn account-flow-submit d-flex align-items-center justify-content-center" href="login.php">الذهاب لتسجيل الدخول</a></div>
                            <a class="account-flow-back" href="resend_verification.php"><i class="fa-regular fa-envelope"></i> لم تصلك الرسالة؟ أعد الإرسال</a>
                        <?php else: ?>
                            <form action="register.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($register_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mb-3">
                                    <label class="form-label" for="full_name">الاسم الكامل</label>
                                    <div class="account-input-wrap"><i class="fa-regular fa-user"></i><input class="form-control" id="full_name" name="full_name" type="text" autocomplete="name" value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="email">البريد الإلكتروني</label>
                                    <div class="account-input-wrap"><i class="fa-regular fa-envelope"></i><input class="form-control" id="email" name="email" type="email" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="password">كلمة المرور</label>
                                    <div class="account-input-wrap"><i class="fa-solid fa-lock"></i><input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required><button class="account-password-toggle" type="button" data-password-toggle="password" aria-label="إظهار كلمة المرور"><i class="fa-regular fa-eye"></i></button></div>
                                    <div class="password-hint"><i class="fa-solid fa-circle-info"></i> 8 أحرف على الأقل</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="password_confirm">تأكيد كلمة المرور</label>
                                    <div class="account-input-wrap"><i class="fa-solid fa-lock"></i><input class="form-control" id="password_confirm" name="password_confirm" type="password" minlength="8" autocomplete="new-password" required><button class="account-password-toggle" type="button" data-password-toggle="password_confirm" aria-label="إظهار كلمة المرور"><i class="fa-regular fa-eye"></i></button></div>
                                </div>
                                <div class="d-grid"><button class="btn account-flow-submit" type="submit">إنشاء الحساب <i class="fa-solid fa-arrow-left me-2"></i></button></div>
                            </form>
                            <a class="account-flow-back" href="login.php"><i class="fa-solid fa-arrow-right"></i> لديك حساب؟ سجل دخولك</a>
                        <?php endif; ?>
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
