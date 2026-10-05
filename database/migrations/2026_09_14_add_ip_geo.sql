-- Migration : géolocalisation des IP pour les statistiques de fréquentation
-- À exécuter sur MySQL (production et développement)

ALTER TABLE `site_visits`
    ADD COLUMN `country` VARCHAR(100) DEFAULT NULL,
    ADD COLUMN `city` VARCHAR(100) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `ip_geo_cache` (
    `ip_address` VARCHAR(45) NOT NULL,
    `country` VARCHAR(100) DEFAULT NULL,
    `city` VARCHAR(100) DEFAULT NULL,
    `resolved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
