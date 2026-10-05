-- Migration : suivi de fréquentation du site (visites de pages)
-- À exécuter sur MySQL (production et développement)

CREATE TABLE IF NOT EXISTS `site_visits` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `url` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `referrer` VARCHAR(255) DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `session_id` VARCHAR(64) DEFAULT NULL,
    `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_visited_at` (`visited_at`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
