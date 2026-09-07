-- Migration : création des tables de brouillon manquantes
-- À exécuter avec : mysql -u <user> -p cool5792_sports_events < migrations/20260831_create_draft_tables.sql

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `draft_events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `category` VARCHAR(50) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `start_time` TIME DEFAULT NULL,
    `end_time` TIME DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `venue` VARCHAR(255) DEFAULT NULL,
    `coordinates` VARCHAR(255) DEFAULT NULL,
    `organisation` VARCHAR(40) DEFAULT NULL,
    `status` ENUM('draft','published') DEFAULT 'draft',
    `has_gpx` TINYINT(1) DEFAULT 0,
    `gpx_path` VARCHAR(255) DEFAULT NULL,
    `gpx_downloadable` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `draft_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `draft_images` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `storage_path` VARCHAR(255) DEFAULT NULL,
    `is_main` TINYINT(1) NOT NULL DEFAULT 0,
    `storage_type` VARCHAR(50) DEFAULT 'local',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    KEY `event_main_image` (`event_id`, `is_main`),
    CONSTRAINT `draft_images_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `draft_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `draft_contacts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `draft_contacts_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `draft_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `draft_parcours` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `category_id` INT(11) DEFAULT NULL,
    `distance` DECIMAL(10,2) DEFAULT NULL,
    `elevation_gain` INT(11) DEFAULT NULL,
    `description` TEXT,
    `gpx_file` VARCHAR(255) DEFAULT NULL,
    `gpx_downloadable` TINYINT(1) DEFAULT 0,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `draft_parcours_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `draft_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
