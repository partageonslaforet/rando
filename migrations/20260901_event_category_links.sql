-- Migration : support multiple tags d'activité par événement
-- Crée les tables de liaison many-to-many pour les catégories
-- À exécuter avec : mysql -u <user> -p cool5792_sports_events < migrations/20260901_event_category_links.sql

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `event_category_links` (
    `event_id` INT(11) NOT NULL,
    `category_id` INT(11) NOT NULL,
    PRIMARY KEY (`event_id`, `category_id`),
    KEY `category_id` (`category_id`),
    CONSTRAINT `event_category_links_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `event_category_links_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `draft_event_category_links` (
    `draft_event_id` INT(11) NOT NULL,
    `category_id` INT(11) NOT NULL,
    PRIMARY KEY (`draft_event_id`, `category_id`),
    KEY `category_id` (`category_id`),
    CONSTRAINT `draft_event_category_links_ibfk_1` FOREIGN KEY (`draft_event_id`) REFERENCES `draft_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `draft_event_category_links_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrer les catégories uniques existantes
INSERT IGNORE INTO `event_category_links` (`event_id`, `category_id`)
SELECT `e`.`id`, `e`.`category_id`
FROM `events` `e`
WHERE `e`.`category_id` IS NOT NULL;

INSERT IGNORE INTO `draft_event_category_links` (`draft_event_id`, `category_id`)
SELECT `de`.`id`, `ec`.`id`
FROM `draft_events` `de`
LEFT JOIN `event_categories` `ec` ON `de`.`category` = `ec`.`code`
WHERE `de`.`category` IS NOT NULL AND `de`.`category` <> '';

SET FOREIGN_KEY_CHECKS = 1;
