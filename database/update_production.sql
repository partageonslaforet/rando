-- Mise à jour production sans perte de données
-- Ne pas exécuter bootstrap.sql en prod (DROP DATABASE + données démo) !
-- Utiliser ce fichier à la place : il ajoute/colonne/enum manquants si besoin.

DELIMITER $$

DROP PROCEDURE IF EXISTS update_production_schema$$

CREATE PROCEDURE update_production_schema()
BEGIN
    -- 1. Ajouter le statut 'expired' si non présent
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'events'
          AND column_name = 'status'
          AND column_type LIKE '%expired%'
    ) THEN
        ALTER TABLE `events`
        MODIFY COLUMN `status` ENUM('draft','published','approved','pending','rejected','expired') DEFAULT 'draft';
    END IF;

    -- 2. Ajouter view_count si non présent
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'events'
          AND column_name = 'view_count'
    ) THEN
        ALTER TABLE `events` ADD COLUMN `view_count` INT(11) DEFAULT 0;
    END IF;

    -- 3. Colonnes manquantes event_images
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'event_images'
          AND column_name = 'is_main'
    ) THEN
        ALTER TABLE `event_images` ADD COLUMN `is_main` TINYINT(1) NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'event_images'
          AND column_name = 'storage_path'
    ) THEN
        ALTER TABLE `event_images` ADD COLUMN `storage_path` VARCHAR(255) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'event_images'
          AND column_name = 'storage_type'
    ) THEN
        ALTER TABLE `event_images` ADD COLUMN `storage_type` VARCHAR(50) DEFAULT 'local';
    END IF;
END$$

DELIMITER ;

CALL update_production_schema();
DROP PROCEDURE IF EXISTS update_production_schema;
