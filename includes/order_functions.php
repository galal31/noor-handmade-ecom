<?php

function order_statuses(): array
{
    return ['قيد المراجعة', 'تم التأكيد', 'جاري الشحن', 'تم التسليم', 'ملغي'];
}

function order_status_colors(): array
{
    return [
        'قيد المراجعة' => 'warning',
        'تم التأكيد' => 'info',
        'جاري الشحن' => 'primary',
        'تم التسليم' => 'success',
        'ملغي' => 'danger',
    ];
}

function order_status_details(): array
{
    return [
        'قيد المراجعة' => ['step' => 1, 'icon' => 'fas fa-receipt', 'text' => 'تم استلام طلبك ونقوم بمراجعته الآن.'],
        'تم التأكيد' => ['step' => 2, 'icon' => 'fas fa-check-circle', 'text' => 'تم تأكيد طلبك وجارٍ تجهيزه للشحن.'],
        'جاري الشحن' => ['step' => 3, 'icon' => 'fas fa-truck', 'text' => 'طلبك خرج للشحن وهو في طريقه إليك.'],
        'تم التسليم' => ['step' => 4, 'icon' => 'fas fa-box-open', 'text' => 'تم تسليم طلبك بنجاح. شكرًا لك!'],
        'ملغي' => ['step' => 0, 'icon' => 'fas fa-ban', 'text' => 'تم إلغاء هذا الطلب. تواصل معنا إذا كنت تحتاج إلى مساعدة.'],
    ];
}

function allowed_order_transitions(): array
{
    return [
        'قيد المراجعة' => ['تم التأكيد', 'ملغي'],
        'تم التأكيد' => ['جاري الشحن', 'ملغي'],
        'جاري الشحن' => ['تم التسليم', 'ملغي'],
        'تم التسليم' => [],
        'ملغي' => ['قيد المراجعة'],
    ];
}

function can_transition_order_status(string $currentStatus, string $newStatus): bool
{
    if ($currentStatus === $newStatus) {
        return true;
    }

    return in_array($newStatus, allowed_order_transitions()[$currentStatus] ?? [], true);
}

function tracking_client_key(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    return hash('sha256', $ip . '|' . $userAgent);
}

function tracking_block_seconds(PDO $pdo, string $clientKey): int
{
    $stmt = $pdo->prepare('SELECT blocked_until FROM order_tracking_attempts WHERE client_key = ?');
    $stmt->execute([$clientKey]);
    $blockedUntil = $stmt->fetchColumn();
    if (!$blockedUntil) {
        return 0;
    }

    return max(0, strtotime($blockedUntil) - time());
}

function clear_tracking_attempts(PDO $pdo, string $clientKey): void
{
    $pdo->prepare('DELETE FROM order_tracking_attempts WHERE client_key = ?')->execute([$clientKey]);
}

function register_invalid_tracking_attempt(PDO $pdo, string $clientKey): int
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT attempts, window_started_at, blocked_until FROM order_tracking_attempts WHERE client_key = ? FOR UPDATE');
        $stmt->execute([$clientKey]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = time();

        if (!$attempt || strtotime($attempt['window_started_at']) < $now - 900) {
            $attempts = 1;
            $windowStartedAt = date('Y-m-d H:i:s', $now);
        } else {
            $attempts = (int) $attempt['attempts'] + 1;
            $windowStartedAt = $attempt['window_started_at'];
        }

        $blockedUntil = $attempts >= 10 ? date('Y-m-d H:i:s', $now + 900) : null;
        $upsert = $pdo->prepare(
            'INSERT INTO order_tracking_attempts (client_key, attempts, window_started_at, blocked_until)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_started_at = VALUES(window_started_at), blocked_until = VALUES(blocked_until)'
        );
        $upsert->execute([$clientKey, $attempts, $windowStartedAt, $blockedUntil]);
        $pdo->commit();
        return $blockedUntil ? 900 : 0;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
