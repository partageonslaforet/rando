-- Migration : ajout des dates d'ouverture/fermeture des inscriptions
-- À exécuter avec : mysql -u <user> -p cool5792_sports_events < migrations/20260901b_add_registration_dates.sql

ALTER TABLE `draft_events`
    ADD COLUMN `registration_opens` DATE NULL,
    ADD COLUMN `registration_closes` DATE NULL;

ALTER TABLE `events`
    ADD COLUMN `registration_opens` DATE NULL,
    ADD COLUMN `registration_closes` DATE NULL;
