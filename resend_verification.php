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
require_once __DIR__ . '/includes/header.php';
?>
<div style="height: 90px"></div>
<main class="container py-5" style="max-width: 620px">
    <div class="card border-0 shadow-sm p-4">
        <h1 class="h3 mb-3">إعادة إرسال رابط التفعيل</h1>
        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label class="form-label" for="email">البريد الإلكتروني</label>
            <input class="form-control mb-3" type="email" id="email" name="email" required>
            <button class="btn btn-primary" type="submit">إرسال رابط جديد</button>
        </form>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
