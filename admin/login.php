<?php

require_once __DIR__ . '/../includes/security.php';
start_secure_session();
send_security_headers();


if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}



require_once __DIR__ . '/../includes/db_connection.php';

$error_message = '';
$login_csrf_token = security_csrf_token('admin_login_csrf_token');
$login_client_key = security_client_key('admin_login');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $blockedSeconds = login_is_blocked($pdo, 'admin', $login_client_key);
    if (!security_csrf_is_valid('admin_login_csrf_token', $_POST['csrf_token'] ?? null)) {
        $error_message = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    } elseif ($blockedSeconds > 0) {
        $error_message = 'محاولات كثيرة غير صحيحة. حاول مرة أخرى بعد ' . max(1, (int) ceil($blockedSeconds / 60)) . ' دقيقة.';
    } elseif (empty($username) || empty($password)) {
        $error_message = 'الرجاء إدخال اسم المستخدم وكلمة المرور.';
    } else {
        try {
            
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            
            if ($admin && password_verify($password, $admin['password'])) {
                
                session_regenerate_id(true); 
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_full_name'] = $admin['full_name']; 
                clear_login_attempts($pdo, 'admin', $login_client_key);
                
                header('Location: index.php');
                exit;
            } else {
                register_failed_login($pdo, 'admin', $login_client_key);
                
                $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
            }
        } catch (PDOException $e) {
            $error_message = "حدث خطأ في النظام. يرجى المحاولة لاحقًا.";
            
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الأدمن</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="card login-card">
        <h3 class="text-center mb-4">لوحة تحكم Noor Handmade</h3>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($login_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label for="username" class="form-label">اسم المستخدم</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">تسجيل الدخول</button>
            </div>
        </form>
    </div>
</body>
</html>
