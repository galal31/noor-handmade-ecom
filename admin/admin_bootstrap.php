<?php

require_once __DIR__ . '/../includes/security.php';
start_secure_session();
send_security_headers();

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db_connection.php';

