-- Migration : ajout de la colonne meeting_city pour isoler la ville
-- À exécuter avec : mysql -u <user> -p <database> < migrations/20260906_add_meeting_city.sql

ALTER TABLE draft_events ADD COLUMN meeting_city VARCHAR(255) DEFAULT NULL;
ALTER TABLE events ADD COLUMN meeting_city VARCHAR(255) DEFAULT NULL;
