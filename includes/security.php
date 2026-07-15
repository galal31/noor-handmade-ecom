<?php

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    session_start();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self' https: data:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

function security_csrf_token(string $key): string
{
    if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

function security_csrf_is_valid(string $key, $value): bool
{
    return is_string($value)
        && isset($_SESSION[$key])
        && is_string($_SESSION[$key])
        && hash_equals($_SESSION[$key], $value);
}

function security_client_key(string $scope): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', $scope . '|' . $ip);
}

function login_is_blocked(PDO $pdo, string $scope, string $clientKey): int
{
    $stmt = $pdo->prepare('SELECT blocked_until FROM login_attempts WHERE scope = ? AND client_key = ?');
    $stmt->execute([$scope, $clientKey]);
    $blockedUntil = $stmt->fetchColumn();
    return $blockedUntil && strtotime($blockedUntil) > time() ? strtotime($blockedUntil) - time() : 0;
}

function register_failed_login(PDO $pdo, string $scope, string $clientKey): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT attempts, window_started_at FROM login_attempts WHERE scope = ? AND client_key = ? FOR UPDATE');
        $stmt->execute([$scope, $clientKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = time();
        if (!$row || strtotime($row['window_started_at']) < $now - 900) {
            $attempts = 1;
            $window = date('Y-m-d H:i:s', $now);
        } else {
            $attempts = (int) $row['attempts'] + 1;
            $window = $row['window_started_at'];
        }
        $blockedUntil = $attempts >= 7 ? date('Y-m-d H:i:s', $now + 900) : null;
        $upsert = $pdo->prepare(
            'INSERT INTO login_attempts (scope, client_key, attempts, window_started_at, blocked_until)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_started_at = VALUES(window_started_at), blocked_until = VALUES(blocked_until)'
        );
        $upsert->execute([$scope, $clientKey, $attempts, $window, $blockedUntil]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function clear_login_attempts(PDO $pdo, string $scope, string $clientKey): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE scope = ? AND client_key = ?')->execute([$scope, $clientKey]);
}

function validate_uploaded_image(array $file, int $maxBytes = 5242880): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('فشل رفع الصورة.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > $maxBytes) {
        throw new RuntimeException('حجم الصورة يجب ألا يتجاوز 5 ميجابايت.');
    }

    $info = @getimagesize($file['tmp_name']);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('الملف المرفوع ليس صورة صالحة.');
    }
    if ($info[0] > 8000 || $info[1] > 8000) {
        throw new RuntimeException('أبعاد الصورة كبيرة جدًا.');
    }
    return ['extension' => $allowed[$info[2]], 'width' => $info[0], 'height' => $info[1]];
}

