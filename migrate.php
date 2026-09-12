<?php
/**
 * Script de migration production pour corriger le schema sans perte de donnees.
 *
 * A placer a la racine du projet (au meme niveau que .env).
 * Securite : admin connecte, execution en CLI, ou cle MIGRATE_KEY dans .env.
 *
 * Usage navigateur : https://rando.partageonslaforet.be/migrate.php?key=<MIGRATE_KEY>
 * Usage CLI : php migrate.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/logs/error.log.php';
require_once __DIR__ . '/includes/functions.php';

// Securite : admin, CLI ou cle MIGRATE_KEY dans .env
initSession();
$providedKey = $_GET['key'] ?? '';
$expectedKey = $_ENV['MIGRATE_KEY'] ?? '';
$isAllowed = (php_sapi_name() === 'cli') || isAdmin() || ($expectedKey !== '' && $providedKey === $expectedKey);
if (!$isAllowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Acces reserve (admin ou CLI).\n";
    if (php_sapi_name() !== 'cli') {
        echo "Tu peux aussi definir MIGRATE_KEY dans .env et appeler ?key=<ta_cle>.\n";
    }
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

function out(string $msg, array $ctx = []): void {
    echo '[' . date('c') . '] ' . $msg . "\n";
    if ($ctx) {
        echo '  ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) . "\n";
    }
    if (function_exists('logError')) {
        logError('migrate', $msg, $ctx);
    } else {
        error_log('migrate: ' . $msg . ' ' . json_encode($ctx));
    }
}

function execOrLog(PDO $pdo, string $sql): void {
    try {
        $pdo->exec($sql);
        $err = $pdo->errorInfo();
        if ($err[0] !== '00000' && $err[1]) {
            out('SQL warning', ['sql' => $sql, 'error' => $err]);
        } else {
            out('OK', ['sql' => substr($sql, 0, 80) . '...']);
        }
    } catch (PDOException $e) {
        out('SQL error', ['sql' => substr($sql, 0, 200), 'error' => $e->getMessage()]);
        throw $e;
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetch();
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

function columnType(PDO $pdo, string $table, string $column): string {
    $stmt = $pdo->prepare("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? strtolower($row['column_type']) : '';
}

try {
    $pdo = getConnection();
    out('Connexion OK', ['db' => DB_NAME]);

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    out('FOREIGN_KEY_CHECKS desactive');

    // --- events : colonnes manquantes ---
    $eventsColumns = [
        'main_image_path' => "ALTER TABLE `events` ADD COLUMN `main_image_path` VARCHAR(255) DEFAULT NULL",
        'main_image' => "ALTER TABLE `events` ADD COLUMN `main_image` VARCHAR(255) DEFAULT NULL",
        'view_count' => "ALTER TABLE `events` ADD COLUMN `view_count` INT DEFAULT 0",
        'registration_opens' => "ALTER TABLE `events` ADD COLUMN `registration_opens` TIME DEFAULT NULL",
        'registration_closes' => "ALTER TABLE `events` ADD COLUMN `registration_closes` TIME DEFAULT NULL",
        'meeting_name' => "ALTER TABLE `events` ADD COLUMN `meeting_name` VARCHAR(255) DEFAULT NULL",
        'meeting_address' => "ALTER TABLE `events` ADD COLUMN `meeting_address` VARCHAR(255) DEFAULT NULL",
        'meeting_city' => "ALTER TABLE `events` ADD COLUMN `meeting_city` VARCHAR(255) DEFAULT NULL",
        'meeting_coordinates' => "ALTER TABLE `events` ADD COLUMN `meeting_coordinates` VARCHAR(255) DEFAULT NULL",
    ];

    foreach ($eventsColumns as $col => $sql) {
        if (!columnExists($pdo, 'events', $col)) {
            execOrLog($pdo, $sql);
        } else {
            out("Colonne events.$col deja presente");
        }
    }

    // --- events.status : ajouter 'expired' a l'enum ---
    $statusType = columnType($pdo, 'events', 'status');
    if ($statusType === '') {
        execOrLog($pdo, "ALTER TABLE `events` ADD COLUMN `status` ENUM('draft','published','approved','pending','rejected','expired') DEFAULT 'draft'");
    } elseif (strpos($statusType, 'expired') === false) {
        execOrLog($pdo, "ALTER TABLE `events` MODIFY COLUMN `status` ENUM('draft','published','approved','pending','rejected','expired') DEFAULT 'draft'");
    } else {
        out("events.status contient deja 'expired'");
    }

    // --- event_images : colonnes manquantes ---
    $eventImagesColumns = [
        'is_main' => "ALTER TABLE `event_images` ADD COLUMN `is_main` TINYINT NOT NULL DEFAULT 0",
        'storage_path' => "ALTER TABLE `event_images` ADD COLUMN `storage_path` VARCHAR(255) DEFAULT NULL",
        'storage_type' => "ALTER TABLE `event_images` ADD COLUMN `storage_type` VARCHAR(50) DEFAULT 'local'",
    ];
    if (tableExists($pdo, 'event_images')) {
        foreach ($eventImagesColumns as $col => $sql) {
            if (!columnExists($pdo, 'event_images', $col)) {
                execOrLog($pdo, $sql);
            } else {
                out("Colonne event_images.$col deja presente");
            }
        }
    } else {
        out('Table event_images absente, creation en cours');
        execOrLog($pdo, "CREATE TABLE IF NOT EXISTS `event_images` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `event_id` INT NOT NULL,
            `image_path` VARCHAR(255) NOT NULL,
            `storage_path` VARCHAR(255) DEFAULT NULL,
            `is_main` TINYINT NOT NULL DEFAULT 0,
            `storage_type` VARCHAR(50) DEFAULT 'local',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // --- event_categories : colonnes manquantes ---
    if (!tableExists($pdo, 'event_categories')) {
        out('Table event_categories absente, creation en cours');
        execOrLog($pdo, "CREATE TABLE IF NOT EXISTS `event_categories` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(50) DEFAULT NULL,
            `name` VARCHAR(255) NOT NULL,
            `icon` VARCHAR(255) DEFAULT NULL,
            `color` VARCHAR(50) DEFAULT NULL,
            `active` TINYINT DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $ecColumns = [
            'code' => "ALTER TABLE `event_categories` ADD COLUMN `code` VARCHAR(50) DEFAULT NULL",
            'icon' => "ALTER TABLE `event_categories` ADD COLUMN `icon` VARCHAR(255) DEFAULT NULL",
            'color' => "ALTER TABLE `event_categories` ADD COLUMN `color` VARCHAR(50) DEFAULT NULL",
            'active' => "ALTER TABLE `event_categories` ADD COLUMN `active` TINYINT DEFAULT 1",
            'sort_order' => "ALTER TABLE `event_categories` ADD COLUMN `sort_order` INT DEFAULT 0",
        ];
        foreach ($ecColumns as $col => $sql) {
            if (!columnExists($pdo, 'event_categories', $col)) {
                execOrLog($pdo, $sql);
            } else {
                out("Colonne event_categories.$col deja presente");
            }
        }
        execOrLog($pdo, "UPDATE `event_categories` SET `code` = LOWER(REPLACE(`name`, ' ', '-')) WHERE `code` IS NULL OR `code` = ''");
    }

    // --- Tables manquantes (homepage et brouillons) ---
    $tables = [
        'event_category_links' => "CREATE TABLE IF NOT EXISTS `event_category_links` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `event_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `event_id` (`event_id`),
            KEY `category_id` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'draft_events' => "CREATE TABLE IF NOT EXISTS `draft_events` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `user_id` INT DEFAULT NULL,
            `title` VARCHAR(255) DEFAULT NULL,
            `description` TEXT,
            `category` VARCHAR(50) DEFAULT NULL,
            `category_id` INT DEFAULT NULL,
            `date` DATE DEFAULT NULL,
            `start_time` TIME DEFAULT NULL,
            `end_time` TIME DEFAULT NULL,
            `registration_opens` TIME DEFAULT NULL,
            `registration_closes` TIME DEFAULT NULL,
            `location` VARCHAR(255) DEFAULT NULL,
            `venue` VARCHAR(255) DEFAULT NULL,
            `coordinates` VARCHAR(255) DEFAULT NULL,
            `meeting_name` VARCHAR(255) DEFAULT NULL,
            `meeting_address` VARCHAR(255) DEFAULT NULL,
            `meeting_city` VARCHAR(255) DEFAULT NULL,
            `meeting_coordinates` VARCHAR(255) DEFAULT NULL,
            `difficulty` ENUM('easy','medium','hard') DEFAULT NULL,
            `max_participants` INT DEFAULT NULL,
            `status` ENUM('draft','published') DEFAULT 'draft',
            `submitted_at` DATETIME DEFAULT NULL,
            `validated_at` DATETIME DEFAULT NULL,
            `validated_by` INT DEFAULT NULL,
            `rejection_reason` VARCHAR(1000) DEFAULT NULL,
            `main_image_path` VARCHAR(255) DEFAULT NULL,
            `main_image` VARCHAR(255) DEFAULT NULL,
            `organizer_id` INT DEFAULT NULL,
            `created_by` INT DEFAULT NULL,
            `organisation` VARCHAR(40) DEFAULT NULL,
            `has_gpx` TINYINT DEFAULT 0,
            `gpx_path` VARCHAR(255) DEFAULT NULL,
            `gpx_downloadable` TINYINT DEFAULT 0,
            `original_event_id` INT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `original_event_id` (`original_event_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'draft_images' => "CREATE TABLE IF NOT EXISTS `draft_images` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `event_id` INT NOT NULL,
            `image_path` VARCHAR(255) NOT NULL,
            `storage_path` VARCHAR(255) DEFAULT NULL,
            `is_main` TINYINT NOT NULL DEFAULT 0,
            `storage_type` VARCHAR(50) DEFAULT 'local',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'draft_parcours' => "CREATE TABLE IF NOT EXISTS `draft_parcours` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `event_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `category_id` INT DEFAULT NULL,
            `distance` DECIMAL(10,2) DEFAULT NULL,
            `elevation_gain` INT DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `gpx_file` VARCHAR(255) DEFAULT NULL,
            `gpx_downloadable` TINYINT DEFAULT 0,
            `price` DECIMAL(10,2) DEFAULT 0.00,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'draft_contacts' => "CREATE TABLE IF NOT EXISTS `draft_contacts` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `event_id` INT NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `event_id` (`event_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'draft_event_category_links' => "CREATE TABLE IF NOT EXISTS `draft_event_category_links` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `draft_event_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `draft_event_id` (`draft_event_id`),
            KEY `category_id` (`category_id`)
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

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    out('FOREIGN_KEY_CHECKS reactive');

    out('Migration terminee avec succes');
} catch (Throwable $e) {
    out('ERREUR FATALE', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    http_response_code(500);
    exit;
}
