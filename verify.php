<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();
require_once 'includes/db_connection.php';

$message_title = "خطأ في التفعيل";
$message_body = "حدث خطأ ما أو أن الرابط غير صالح.";
$message_icon = "error";

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND account_status = 'pending_verification' AND verification_expires_at >= NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'active', verification_token = NULL, verification_expires_at = NULL WHERE id = ?");
        if ($stmt->execute([$user['id']])) {
            $message_title = "تم تفعيل حسابك بنجاح!";
            $message_body = "أهلاً بك في عالم Noor Handmade. سيتم الآن توجيهك إلى صفحة تسجيل الدخول.";
            $message_icon = "success";
        }
    } else {
        $message_body = "هذا الرابط غير صالح أو تم استخدامه مسبقًا.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفعيل الحساب - Noor Handmade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=El+Messiri:wght@600;700&display=swap" rel="stylesheet">
    
    <?php if($message_icon == 'success'): ?>
    <meta http-equiv="refresh" content="5;url=login.php">
    <?php endif; ?>

    <style>
        :root {
            --primary-color: #16a085;
            --secondary-color: #e67e22;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --font-headings: 'El Messiri', serif;
            --font-body: 'Cairo', sans-serif;
        }
        body {
            font-family: var(--font-body);
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .verify-card {
            width: 100%;
            max-width: 500px;
            padding: 3rem;
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .icon-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px auto;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: #fff;
        }
        .icon-container.success { background-color: var(--primary-color); }
        .icon-container.error { background-color: #e74c3c; }
        .verify-title {
            font-family: var(--font-headings);
            color: var(--dark-color);
            font-weight: 700;
        }
        .btn-main {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 700;
            transition: background-color 0.3s ease;
        }
        .btn-main:hover {
            background-color: #117a65;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="card verify-card">
        <div class="icon-container <?= $message_icon ?>">
            <span><?= ($message_icon == 'success') ? '✓' : '✗' ?></span>
        </div>
        <h1 class="verify-title"><?= htmlspecialchars($message_title) ?></h1>
        <p class="lead text-muted"><?= htmlspecialchars($message_body) ?></p>
        <a href="login.php" class="btn btn-main mt-3">اذهب إلى صفحة تسجيل الدخول</a>
    </div>
</body>
</html>
