<?php

$localConfig = __DIR__ . '/local_config.php';
if (is_file($localConfig)) {
    require $localConfig;
}

function app_config(string $key, $default = null)
{
    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }
    return $GLOBALS['NOOR_CONFIG'][$key] ?? $default;
}

function app_base_url(): string
{
    $configured = rtrim((string) app_config('APP_URL', ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/noor/index.php')));
    return $scheme . '://' . $host . rtrim($scriptDir, '/');
}

function app_debug_enabled(): bool
{
    return filter_var(app_config('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
}
