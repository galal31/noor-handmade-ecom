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
require_once __DIR__ . '/includes/header.php';
?>
<div style="height:90px"></div><main class="container py-5" style="max-width:620px"><div class="card border-0 shadow-sm p-4">
<h1 class="h3 mb-3">تعيين كلمة مرور جديدة</h1>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="login.php">تسجيل الدخول</a></div>
<?php elseif ($resetUserId): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="password">كلمة المرور الجديدة</label><input class="form-control mb-3" id="password" name="password" type="password" minlength="8" required><label class="form-label" for="password_confirm">تأكيد كلمة المرور</label><input class="form-control mb-3" id="password_confirm" name="password_confirm" type="password" minlength="8" required><button class="btn btn-primary" type="submit">حفظ كلمة المرور</button></form>
<?php else: ?><div class="alert alert-danger">الرابط غير صالح أو انتهت صلاحيته.</div><?php endif; ?>
</div></main><?php require_once __DIR__ . '/includes/footer.php'; ?>

