<?php

declare(strict_types=1);

if (!function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $configuredUrl = (string) ($_ENV['APP_URL'] ?? '');
        $configuredPath = parse_url($configuredUrl, PHP_URL_PATH);
        $basePath = is_string($configuredPath) ? rtrim($configuredPath, '/') : '';

        // Return a host-relative URL so the same page works through localhost,
        // the server LAN IP and any future local hostname.
        return ($basePath === '' ? '' : $basePath)
            . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . app_url($path));
        exit;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['_flash'][$key] = $message;
            return null;
        }

        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($value) ? $value : null;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        return (string) ($_SESSION['_old'][$key] ?? $default);
    }
}
