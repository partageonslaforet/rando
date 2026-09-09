DROP DATABASE IF EXISTS `cool5792_sports_events`;
CREATE DATABASE IF NOT EXISTS `cool5792_sports_events`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `cool5792_sports_events`;

SET FOREIGN_KEY_CHECKS = 0;

-- Utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `first_name` VARCHAR(255) DEFAULT NULL,
    `last_name` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('admin','organizer','user') DEFAULT 'user',
    `is_active` TINYINT(1) DEFAULT 1,
    `email_verified` TINYINT(1) DEFAULT 0,
    `remember_token` VARCHAR(100) DEFAULT NULL,
    `remember_token_expires_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens "Se souvenir de moi"
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catégories d'événements
CREATE TABLE IF NOT EXISTS `event_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(255) DEFAULT NULL,
    `color` VARCHAR(50) DEFAULT NULL,
    `active` TINYINT(1) DEFAULT 1,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `code` (`code`),
    KEY `active_sort` (`active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profils d'organisateurs (utilisé par event-detail.php)
CREATE TABLE IF NOT EXISTS `organizer_profiles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `address` TEXT,
    `phone` VARCHAR(50),
    `email` VARCHAR(255),
    `website` VARCHAR(255),
    `logo_path` VARCHAR(255),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `organizer_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Événements
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(50) DEFAULT NULL,
    `category_id` INT(11) DEFAULT NULL,
    `date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME DEFAULT NULL,
    `location` VARCHAR(255) DEFAULT NULL,
    `venue` VARCHAR(255) DEFAULT NULL,
    `coordinates` VARCHAR(255) DEFAULT NULL,
    `difficulty` ENUM('easy','medium','hard') DEFAULT NULL,
    `max_participants` INT(11) DEFAULT NULL,
    `status` ENUM('draft','published','approved','pending','rejected','expired') DEFAULT 'draft',
    `submitted_at` DATETIME DEFAULT NULL,
    `validated_at` DATETIME DEFAULT NULL,
    `validated_by` INT(11) DEFAULT NULL,
    `rejection_reason` VARCHAR(1000) DEFAULT NULL,
    `main_image_path` VARCHAR(255) DEFAULT NULL,
    `main_image` VARCHAR(255) DEFAULT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `organizer_id` INT(11) DEFAULT NULL,
    `created_by` INT(11) DEFAULT NULL,
    `organisation` VARCHAR(40) DEFAULT NULL,
    `has_gpx` TINYINT(1) DEFAULT 0,
    `gpx_path` VARCHAR(255) DEFAULT NULL,
    `gpx_downloadable` TINYINT(1) DEFAULT 0,
    `view_count` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category_id` (`category_id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    KEY `date` (`date`),
    KEY `status_date` (`status`, `date`),
    CONSTRAINT `events_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL,
    CONSTRAINT `events_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Images d'événements
CREATE TABLE IF NOT EXISTS `event_images` (
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
    CONSTRAINT `event_images_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participants aux événements
CREATE TABLE IF NOT EXISTS `event_participants` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcours / routes (modèle Event.php)
CREATE TABLE IF NOT EXISTS `event_routes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `category_id` INT(11) DEFAULT NULL,
    `distance` DECIMAL(10,2) DEFAULT NULL,
    `elevation` INT(11) DEFAULT NULL,
    `gpx_file` VARCHAR(255) DEFAULT NULL,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `event_routes_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts des événements
CREATE TABLE IF NOT EXISTS `event_contacts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `event_contacts_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcours / parcours (template event-detail.php)
CREATE TABLE IF NOT EXISTS `event_parcours` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `category_id` INT(11) DEFAULT NULL,
    `distance` DECIMAL(10,2) DEFAULT NULL,
    `elevation_gain` INT(11) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `gpx_file` VARCHAR(255) DEFAULT NULL,
    `gpx_downloadable` TINYINT(1) DEFAULT 0,
    `price` DECIMAL(10,2) DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    CONSTRAINT `event_parcours_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commentaires (compteur et API)
CREATE TABLE IF NOT EXISTS `event_comments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `event_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `content` TEXT NOT NULL,
    `rating` TINYINT(1) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_id` (`event_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `event_comments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `event_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Catégories par défaut
INSERT INTO `event_categories` (`code`, `name`, `icon`, `color`, `active`, `sort_order`) VALUES
('hiking', 'Randonnée', 'fa-person-hiking', '#4CAF50', 1, 1),
('running', 'Course à pied', 'fa-person-running', '#2196F3', 1, 2),
('cycling', 'Vélo', 'fa-bicycle', '#FF9800', 1, 3)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Utilisateur / organisateur démo (pas de données personnelles réelles)
INSERT INTO `users` (`email`, `password`, `name`, `role`, `is_active`, `email_verified`) VALUES
('demo@example.local', NULL, 'Organisateur démo', 'organizer', 1, 1);

INSERT INTO `organizer_profiles` (`user_id`, `name`, `description`, `address`, `phone`, `email`, `website`, `logo_path`) VALUES
(1, 'Club Nature Ardenne', 'Association locale fictive de randonnées pédestres et de VTT.', 'Rue de la Forêt 1, 5500 Dinant', '+32 471 23 45 67', 'contact@example.local', 'https://example.local', '/uploads/organizers/logo.png');

-- Événements de démonstration
INSERT INTO `events` (`title`, `description`, `category`, `category_id`, `date`, `start_time`, `end_time`, `location`, `venue`, `coordinates`, `difficulty`, `max_participants`, `status`, `submitted_at`, `validated_at`, `validated_by`, `rejection_reason`, `main_image_path`, `main_image`, `user_id`, `organizer_id`, `created_by`, `organisation`, `has_gpx`, `gpx_path`, `gpx_downloadable`) VALUES
('Randonnée familiale en forêt de Soignes', 'Une belle balade en famille à travers les sentiers de la forêt de Soignes. Niveau facile, accessible à tous.', 'hiking', 1, '2026-09-15', '09:00:00', '12:00:00', 'Forêt de Soignes', 'Parking de la Hulpe', '50.7320,4.4690', 'easy', 20, 'published', '2026-08-29 12:00:00', '2026-08-29 12:00:00', 1, NULL, '/uploads/events/randonnee.jpg', '/uploads/events/randonnee.jpg', 1, 1, 1, '1', 0, NULL, 0),
('VTT sportif dans les crêtes de la Famenne', 'Boucle VTT exigeante sur les crêtes de la Famenne avec de beaux panoramas. GPX fourni.', 'cycling', 3, '2026-09-20', '09:30:00', '13:00:00', 'Gesves', 'Place de la Hestre', '50.4050,5.0680', 'hard', 15, 'published', '2026-08-29 12:00:00', '2026-08-29 12:00:00', 1, NULL, '/uploads/events/vtt.jpg', '/uploads/events/vtt.jpg', 1, 1, 1, '1', 1, 'uploads/gpx/vtt.gpx', 1),
('Trail du bois de la Cambre', 'Sortie trail de 12 km en forêt, allure modérée. Prévoir bonnes chaussures.', 'running', 2, '2026-10-05', '08:00:00', '11:00:00', 'Bruxelles', 'Allée des Amazones', '50.8130,4.3670', 'medium', 50, 'published', '2026-08-29 12:00:00', '2026-08-29 12:00:00', 1, NULL, '/uploads/events/trail.jpg', '/uploads/events/trail.jpg', 1, 1, 1, '1', 0, NULL, 0);

-- Images principales
INSERT INTO `event_images` (`event_id`, `image_path`, `is_main`) VALUES
(1, 'uploads/events/randonnee.jpg', 1),
(2, 'uploads/events/vtt.jpg', 1),
(3, 'uploads/events/trail.jpg', 1);

-- Parcours pour event-detail.php
INSERT INTO `event_parcours` (`event_id`, `name`, `category_id`, `distance`, `elevation_gain`, `gpx_file`, `price`) VALUES
(1, 'Parcours facile', 1, 8.50, 120, NULL, 0.00),
(2, 'Boucle VTT 25 km', 3, 25.00, 350, 'uploads/gpx/vtt.gpx', 0.00),
(3, 'Trail 12 km', 2, 12.00, 220, NULL, 0.00);

-- Pour créer un compte admin local après import, exécuter :
-- INSERT INTO `users` (`email`, `password`, `name`, `role`, `is_active`, `email_verified`)
-- VALUES ('admin@example.local', '$2y$...HASH...', 'Administrateur test', 'admin', 1, 1);