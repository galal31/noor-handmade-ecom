<?php



require_once __DIR__ . '/security.php';
start_secure_session();
send_security_headers();



if (!isset($_SESSION['user_id'])) {
    
    header('Location: login.php');
    
    exit;
}

require_once __DIR__ . '/db_connection.php';
$authStmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND account_status = 'active' LIMIT 1");
$authStmt->execute([(int) $_SESSION['user_id']]);
$authenticatedUser = $authStmt->fetch(PDO::FETCH_ASSOC);
if (!$authenticatedUser) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php?status=account_unavailable');
    exit;
}
$_SESSION['user_full_name'] = $authenticatedUser['full_name'];
$_SESSION['user_email'] = $authenticatedUser['email'];
