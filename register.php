<?php

require_once __DIR__ . '/includes/security.php';
start_secure_session();
send_security_headers();


if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}


require_once 'includes/db_connection.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success_message = '';
$register_csrf_token = security_csrf_token('register_csrf_token');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $email = strtolower($email);

    if (!security_csrf_is_valid('register_csrf_token', $_POST['csrf_token'] ?? null)) {
        $errors[] = 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.';
    }
    if (empty($full_name) || empty($email) || empty($password)) {
        $errors[] = "جميع الحقول مطلوبة.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "البريد الإلكتروني غير صحيح.";
    }
    if ($password !== $password_confirm) {
        $errors[] = "كلمتا المرور غير متطابقتين.";
    }
    if (strlen($password) < 8) {
        $errors[] = "يجب أن تكون كلمة المرور 8 أحرف على الأقل.";
    }

    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "هذا البريد الإلكتروني مسجل بالفعل.";
        }
    }

    
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(50));

        
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, verification_token, verification_expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
        if ($stmt->execute([$full_name, $email, $password_hash, $token])) {
            $newUserId = (int) $pdo->lastInsertId();
            try {
                send_verification_email($email, $full_name, $token);
                $success_message = 'تم التسجيل بنجاح! أرسلنا رابط تفعيل صالحًا لمدة 24 ساعة إلى بريدك.';
            } catch (Throwable $e) {
                $pdo->prepare('DELETE FROM users WHERE id = ? AND account_status = ?')->execute([$newUserId, 'pending_verification']);
                $errors[] = mail_failure_diagnostic('registration_verification', $e) . ' لم يتم إنشاء الحساب ويمكنك المحاولة مرة أخرى.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حساب جديد - Noor Handmade</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&family=El+Messiri:wght@600;700&display=swap"
        rel="stylesheet">

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
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .auth-form-section {
            padding: 3rem;
        }

        .auth-form-section .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ddd;
        }

        .auth-form-section .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(22, 160, 133, 0.25);
        }

        .auth-title {
            font-family: var(--font-headings);
            color: var(--dark-color);
            font-weight: 700;
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #117a65;
            color: #fff;
        }

        .auth-image-section {
            background-size: cover;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 991px) {
            .auth-image-section {
                display: none;
            }

            .auth-form-section {
                padding: 2rem;
            }
        }
    </style>
</head>

<body>
    <?php include("includes/header.php"); ?>
    <div class="container auth-container">
        <div class="col-lg-10 col-md-12">
            <div class="card auth-card">
                <div class="row g-0">
                    <div class="col-lg-7">
                        <div class="auth-form-section">
                            <div class="text-center mb-4">
                                <h1 class="auth-title">أنشئ حسابك</h1>
                                <p class="text-muted">انضم إلينا واكتشف عالماً من القطع الفريدة.</p>
                            </div>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($errors as $error): ?>
                                        <p class="mb-0"><?= htmlspecialchars($error) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success_message): ?>
                                <div class="alert alert-success text-center">
                                    <h4 class="alert-heading">رائع!</h4>
                                    <p><?= htmlspecialchars($success_message) ?></p>
                                    <hr>
                                    <p class="mb-0">لم يصلك الإيميل؟ <a href="#">أعد الإرسال</a></p>
                                </div>
                            <?php else: ?>
                                <form action="register.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($register_csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="mb-3">
                                        <label class="form-label">الاسم الكامل</label>
                                        <input type="text" name="full_name" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">البريد الإلكتروني</label>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">كلمة المرور</label>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">تأكيد كلمة المرور</label>
                                        <input type="password" name="password_confirm" class="form-control" required>
                                    </div>
                                    <div class="d-grid mt-4">
                                        <button type="submit" class="btn btn-submit">إنشاء الحساب</button>
                                    </div>
                                    <div class="text-center mt-4">
                                        <small class="text-muted">لديك حساب بالفعل؟ <a href="login.php"
                                                style="color: var(--primary-color);">سجل دخولك الآن</a></small>
                                    </div>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                    <div class="col-lg-5 auth-image-section">
                        <a href="index.php">
                            <img src="images/logo.jpeg" alt="Noor Handmade Logo" width="150"
                                style="border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include("includes/footer.php"); ?>
</body>

</html>
