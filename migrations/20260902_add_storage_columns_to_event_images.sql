-- Harmonisation des colonnes de stockage des images publiées
ALTER TABLE `event_images` ADD COLUMN `storage_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `event_images` ADD COLUMN `storage_type` VARCHAR(50) DEFAULT 'local';
