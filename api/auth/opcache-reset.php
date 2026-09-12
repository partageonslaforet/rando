<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$provided = $_GET['key'] ?? '';
$expected = $_ENV['MIGRATE_KEY'] ?? '';

if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Clé invalide']);
    exit;
}

$files = [
    __DIR__ . '/../../includes/mailer.php',
    __DIR__ . '/../../api/auth/register.php',
    __DIR__ . '/../../api/auth/forgot-password.php',
    __DIR__ . '/../../logs/error.log.php',
    __DIR__ . '/../../config/mail.php',
    __DIR__ . '/../../config/mail.local.php',
];

$invalidated = [];
foreach ($files as $file) {
    if (file_exists($file) && function_exists('opcache_invalidate')) {
        $invalidated[$file] = opcache_invalidate($file, true);
    } else {
        $invalidated[$file] = 'skipped';
    }
}

$reset = false;
if (function_exists('opcache_reset')) {
    $reset = opcache_reset();
}

echo json_encode([
    'status' => 'ok',
    'opcache_reset' => $reset,
    'invalidated' => $invalidated,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
