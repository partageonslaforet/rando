-- Migration : protection anti-scan (ban IP après 404 répétés)
-- À appliquer via phpMyAdmin.

-- IPs bannies (auto ou manuel). L'IP admin 85.201.89.175 est whitelistée en dur
-- dans includes/ip_ban.php et ne peut jamais être insérée ici par le code.
CREATE TABLE IF NOT EXISTS `banned_ips` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `hits` INT UNSIGNED NOT NULL DEFAULT 0,
    `banned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_hit_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des hits 404 (fenêtre glissante pour le calcul du ban).
CREATE TABLE IF NOT EXISTS `ip_404_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `uri` VARCHAR(500) NOT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `hit_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ip_time` (`ip_address`, `hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
