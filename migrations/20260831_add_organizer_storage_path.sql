-- Migration : ajoute la colonne storage_path manquante à organizer_profiles
-- Nécessaire pour save_draft.php

ALTER TABLE `organizer_profiles`
    ADD COLUMN `storage_path` VARCHAR(255) DEFAULT NULL AFTER `logo_path`;
