<?php
declare(strict_types=1);

$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
            $dotenv->load();
        } catch (Throwable $e) {
            // ignore
        }
    } elseif (function_exists('parse_ini_file')) {
        $ini = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if ($ini !== false) {
            foreach ($ini as $key => $value) {
                if (is_string($value)) {
                    $value = trim($value, "\"'");
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

require_once __DIR__ . '/../../logs/error.log.php';

header('Content-Type: application/json; charset=utf-8');

$logDir = __DIR__ . '/../../logs';
$logFile = $logDir . '/error.log';

$diag = [
    'env_path_exists' => file_exists($envPath),
    'log_dir_exists' => is_dir($logDir),
    'log_dir_writable' => is_dir($logDir) && is_writable($logDir),
    'log_file_exists' => file_exists($logFile),
    'log_file_writable' => file_exists($logFile) ? is_writable($logFile) : is_writable($logDir),
    'php_error_log' => ini_get('error_log'),
    'disable_functions' => ini_get('disable_functions'),
    'mail_function_exists' => function_exists('mail'),
    'mail_disabled' => in_array('mail', array_map('trim', explode(',', ini_get('disable_functions'))), true),
];

// Test ecriture dans le fichier de log
if (function_exists('logError')) {
    $testMsg = 'TEST_LOG_WRITABLE_' . time();
    logError('api/auth/test-logs.php', $testMsg, ['source' => 'diagnostic']);
    $diag['log_test_message'] = $testMsg;
    $diag['log_last_lines'] = @file_exists($logFile) ? array_slice(file($logFile), -5) : [];
}

// Config mail (reduite, sans secret)
require_once __DIR__ . '/../../config/mail.php';
$diag['mail'] = [
    'host' => defined('MAIL_HOST') ? MAIL_HOST : null,
    'port' => defined('MAIL_PORT') ? MAIL_PORT : null,
    'username_set' => defined('MAIL_USERNAME') ? (MAIL_USERNAME !== '') : null,
    'password_set' => defined('MAIL_PASSWORD') ? (MAIL_PASSWORD !== '') : null,
    'encryption' => defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : null,
    'from' => defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : null,
];

// Surcharge locale
$localCfg = __DIR__ . '/../../config/mail.local.php';
$docRootLocal = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/config/mail.local.php';
$diag['mail_local_php_exists'] = file_exists($localCfg) || file_exists($docRootLocal);
$diag['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? null;

echo json_encode($diag, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
