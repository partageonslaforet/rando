-- Migration : horaires par parcours (fenêtre d'inscriptions + départ)
-- À appliquer via phpMyAdmin.
-- Les horaires d'inscription et de départ appartiennent désormais à chaque
-- parcours (étape 2 du formulaire), pas à la description de l'événement.
-- events.start_time / end_time (horaire de l'activité) et les anciennes
-- events.registration_* (données existantes) restent inchangés.

ALTER TABLE `event_parcours`
    ADD COLUMN `registration_opens` TIME DEFAULT NULL AFTER `price`,
    ADD COLUMN `registration_closes` TIME DEFAULT NULL AFTER `registration_opens`,
    ADD COLUMN `departure_time` TIME DEFAULT NULL AFTER `registration_closes`,
    ADD COLUMN `departure_flexible` TINYINT(1) NOT NULL DEFAULT 0 AFTER `departure_time`;


ALTER TABLE `draft_parcours`
    ADD COLUMN `registration_opens` TIME DEFAULT NULL AFTER `price`,
    ADD COLUMN `registration_closes` TIME DEFAULT NULL AFTER `registration_opens`,
    ADD COLUMN `departure_time` TIME DEFAULT NULL AFTER `registration_closes`,
    ADD COLUMN `departure_flexible` TINYINT(1) NOT NULL DEFAULT 0 AFTER `departure_time`;
-- Nettoyage optionnel : à exécuter UNIQUEMENT si la première version de cette
-- migration (colonnes departure_* sur events/draft_events) a déjà été appliquée.
-- ALTER TABLE `events` DROP COLUMN `departure_time`, DROP COLUMN `departure_flexible`;
-- ALTER TABLE `draft_events` DROP COLUMN `departure_time`, DROP COLUMN `departure_flexible`;
