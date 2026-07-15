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
require_once __DIR__ . '/includes/header.php';
?>
<div style="height:90px"></div><main class="container py-5" style="max-width:620px"><div class="card border-0 shadow-sm p-4">
<h1 class="h3 mb-3">استعادة كلمة المرور</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><label for="email" class="form-label">البريد الإلكتروني</label><input id="email" name="email" type="email" class="form-control mb-3" required><button class="btn btn-primary" type="submit">إرسال رابط الاستعادة</button></form>
</div></main><?php require_once __DIR__ . '/includes/footer.php'; ?>
