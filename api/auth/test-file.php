<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$files = [
    'public/index.php' => __DIR__ . '/../../public/index.php',
    'includes/mailer.php' => __DIR__ . '/../../includes/mailer.php',
    'includes/csrf.php' => __DIR__ . '/../../includes/csrf.php',
    'logs/error.log.php' => __DIR__ . '/../../logs/error.log.php',
    'config/mail.local.php' => __DIR__ . '/../../config/mail.local.php',
    'api/auth/register.php' => __DIR__ . '/../../api/auth/register.php',
    'api/auth/login.php' => __DIR__ . '/../../api/auth/login.php',
    'api/auth/forgot-password.php' => __DIR__ . '/../../api/auth/forgot-password.php',
    'templates/modals/login.php' => __DIR__ . '/../../templates/modals/login.php',
    'templates/modals/register.php' => __DIR__ . '/../../templates/modals/register.php',
    'public/assets/js/core/auth.js' => __DIR__ . '/../../public/assets/js/core/auth.js',
];

$result = [];
foreach ($files as $name => $path) {
    $result[$name] = [
        'exists' => file_exists($path),
        'md5' => file_exists($path) ? md5_file($path) : null,
        'mtime' => file_exists($path) ? date('c', filemtime($path)) : null,
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
