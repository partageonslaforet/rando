-- Migration: Ajout des tables d'abonnement aux événements
-- Date: 2026-09-23
-- Description: Crée les tables pour gérer les abonnements aux notifications d'événements

SET FOREIGN_KEY_CHECKS = 0;

-- Table des abonnés
CREATE TABLE IF NOT EXISTS `event_subscribers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `verification_token` VARCHAR(255) UNIQUE,
    `verified_at` DATETIME,
    `is_active` BOOLEAN DEFAULT 1,
    `notification_frequency` ENUM('immediate', 'weekly') DEFAULT 'immediate',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `verified_at` (`verified_at`),
    KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des préférences d'abonnement (catégories)
CREATE TABLE IF NOT EXISTS `subscriber_preferences` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `subscriber_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subscriber_category` (`subscriber_id`, `category_id`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `event_subscribers` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table d'historique des notifications envoyées
CREATE TABLE IF NOT EXISTS `subscriber_notifications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `subscriber_id` INT NOT NULL,
    `event_id` INT NOT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `subscriber_event` (`subscriber_id`, `event_id`),
    FOREIGN KEY (`subscriber_id`) REFERENCES `event_subscribers` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
