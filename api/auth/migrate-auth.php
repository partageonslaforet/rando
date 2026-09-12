<?php
/**
 * Migration auth : cree les tables manquantes pour mots de passe oublie et verification e-mail.
 *
 * Usage : /api/auth/migrate-auth.php?key=<MIGRATE_KEY>
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../logs/error.log.php';

initSession();
$provided = $_GET['key'] ?? '';
$expected = $_ENV['MIGRATE_KEY'] ?? '';
if (!isAdmin() && !($expected !== '' && $provided === $expected)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Accès refusé.\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

function out(string $msg, array $ctx = []): void {
    echo '[' . date('c') . '] ' . $msg . "\n";
    if ($ctx) {
        echo '  ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (function_exists('logError')) {
        logError('migrate-auth', $msg, $ctx);
    }
}

function execOrLog(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
        $err = $pdo->errorInfo();
        if ($err[0] !== '00000' && $err[1]) {
            out('SQL warning', ['sql' => substr($sql, 0, 80) . '...', 'error' => $err]);
        } else {
            out('OK', ['sql' => substr($sql, 0, 80) . '...']);
        }
    } catch (PDOException $e) {
        out('SQL error', ['sql' => substr($sql, 0, 200), 'error' => $e->getMessage()]);
        throw $e;
    }
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetch();
}

try {
    $pdo = getConnection();
    out('Connexion OK', ['db' => DB_NAME]);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $tables = [
        'password_reset_tokens' => "CREATE TABLE `password_reset_tokens` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `token_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `token_hash` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'email_verification_tokens' => "CREATE TABLE `email_verification_tokens` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `token_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `token_hash` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'remember_tokens' => "CREATE TABLE `remember_tokens` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `token_hash` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `token_hash` (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $name => $sql) {
        if (!tableExists($pdo, $name)) {
            out("Creation de la table $name");
            execOrLog($pdo, $sql);
        } else {
            out("Table $name deja presente");
        }
    }

    $columns = [
        'users' => [
            'name' => "ALTER TABLE `users` ADD COLUMN `name` VARCHAR(255) DEFAULT NULL",
            'role' => "ALTER TABLE `users` ADD COLUMN `role` ENUM('admin','organizer','user') DEFAULT 'user'",
            'is_active' => "ALTER TABLE `users` ADD COLUMN `is_active` TINYINT DEFAULT 1",
            'email_verified' => "ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT DEFAULT 0",
            'created_at' => "ALTER TABLE `users` ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE `users` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
            'remember_token' => "ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(100) DEFAULT NULL",
            'remember_token_expires_at' => "ALTER TABLE `users` ADD COLUMN `remember_token_expires_at` DATETIME DEFAULT NULL",
        ],
    ];

    foreach ($columns as $table => $cols) {
        foreach ($cols as $column => $sql) {
            if (!columnExists($pdo, $table, $column)) {
                out("Ajout colonne $table.$column");
                execOrLog($pdo, $sql);
            } else {
                out("Colonne $table.$column deja presente");
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    out('Migration auth terminee avec succes');
} catch (Throwable $e) {
    out('ERREUR FATALE', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    exit;
}
