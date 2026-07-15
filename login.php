<?php
require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'includes/db_connection.php';

$errors = [];
$login_csrf_token = security_csrf_token('user_login_csrf_token');
$login_client_key = security_client_key('user_login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $blockedSeconds = login_is_blocked($pdo, 'user', $login_client_key);
    if (!security_csrf_is_valid('user_login_csrf_token', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    } elseif ($blockedSeconds > 0) {
        $errors[] = 'محاولات كثيرة غير صحيحة. حاول بعد ' . max(1, (int) ceil($blockedSeconds / 60)) . ' دقيقة.';
    } elseif (empty($email) || empty($password)) {
        $errors[] = "الرجاء إدخال البريد الإلكتروني وكلمة المرور.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['account_status'] === 'active') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                clear_login_attempts($pdo, 'user', $login_client_key);
                header('Location: index.php');
                exit;
            } elseif ($user['account_status'] === 'pending_verification') {
                $errors[] = "حسابك غير مفعل. الرجاء مراجعة بريدك الإلكتروني لتفعيل الحساب.";
            } elseif ($user['account_status'] === 'suspended') {
                $errors[] = "لقد تم تعليق حسابك. يرجى التواصل مع الإدارة.";
            }
        } else {
            register_failed_login($pdo, 'user', $login_client_key);
            $errors[] = "البريد الإلكتروني أو كلمة المرور غير صحيحة.";
        }
    }
}
// تضمين الهيدر بعد منطق المعالجة لتجنب الأخطاء
require_once 'includes/header.php';
?>
<style>
    body {
        background-color: var(--light-color);
    }

    .login-container {
        min-height: calc(100vh - 85px);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-card {
        border: none;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    }

    .login-form-section {
        padding: 4rem;
    }

    .login-form-section .form-control {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #ddd;
    }

    .login-form-section .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(22, 160, 133, 0.25);
    }

    .login-title {
        font-family: var(--font-headings);
        color: var(--dark-color);
        font-weight: 700;
    }

    .btn-login {
        background-color: var(--primary-color);
        color: #fff;
        border: none;
        border-radius: 10px;
        padding: 12px;
        font-weight: 700;
        transition: background-color 0.3s ease;
    }

    .btn-login:hover {
        background-color: #117a65;
        color: #fff;
    }

    .login-image-section {
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-support-links {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 1.25rem;
    }

    .login-support-link {
        display: flex;
        min-height: 48px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 12px;
        border: 1px solid rgba(22, 160, 133, 0.2);
        border-radius: 12px;
        background: #f6fcfa;
        color: #117a65;
        font-size: 0.88rem;
        font-weight: 700;
        text-decoration: none;
        transition: 0.2s ease;
    }

    .login-support-link:hover {
        border-color: var(--primary-color);
        background: #e8f7f3;
        color: #0f6d5b;
        transform: translateY(-1px);
    }

    @media (max-width: 991px) {
        .login-image-section {
            display: none;
        }

        .login-form-section {
            padding: 2rem;
        }

        .login-support-links {
            grid-template-columns: 1fr;
        }
    }
</style>
<div class="container login-container">
    <div class="col-lg-10 col-md-12">
        <div class="card login-card">
            <div class="row g-0">
                <div class="col-lg-7">
                    <div class="login-form-section">
                        <div class="text-center mb-5">
                            <h1 class="login-title mt-3">مرحباً بعودتك!</h1>
                            <p class="text-muted">سجل دخولك لمتابعة إبداعاتنا.</p>
                        </div>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <form action="login.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($login_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-login">تسجيل الدخول</button>
                            </div>
                            <div class="text-center mt-4">
                                <small class="text-muted">ليس لديك حساب؟ <a href="register.php"
                                        style="color: var(--primary-color);">أنشئ حسابًا جديدًا</a></small>
                            </div>
                        </form>
                        <div class="login-support-links">
                            <a class="login-support-link" href="forgot_password.php"><i class="fa-solid fa-key"></i> نسيت كلمة المرور؟</a>
                            <a class="login-support-link" href="resend_verification.php"><i class="fa-regular fa-envelope"></i> إعادة إرسال رابط التفعيل</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 login-image-section">
                    <img src="images/logo.jpeg" alt="Noor Handmade Logo" width="150"
                        style="border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
