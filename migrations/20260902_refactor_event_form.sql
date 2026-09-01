-- Migration PostgreSQL : refonte du formulaire de création d'événement
-- - Suppression des colonnes heure de départ/fin
-- - Passage des inscriptions en TIME (heure sans date)
-- - Suppression de la colonne organisateur du formulaire événement
-- - Ajout de l'adresse du jour (nom, adresse, coordonnées)
-- À exécuter avec : psql -U <user> -d plf_dev -f migrations/20260902_refactor_event_form.sql

BEGIN;

-- Suppression des heures de départ/fin
ALTER TABLE draft_events DROP COLUMN IF EXISTS start_time;
ALTER TABLE draft_events DROP COLUMN IF EXISTS end_time;
ALTER TABLE events DROP COLUMN IF EXISTS start_time;
ALTER TABLE events DROP COLUMN IF EXISTS end_time;

-- Passage des inscriptions de DATE à TIME (suppression/recréation)
ALTER TABLE draft_events DROP COLUMN IF EXISTS registration_opens;
ALTER TABLE draft_events DROP COLUMN IF EXISTS registration_closes;
ALTER TABLE events DROP COLUMN IF EXISTS registration_opens;
ALTER TABLE events DROP COLUMN IF EXISTS registration_closes;

ALTER TABLE draft_events ADD COLUMN registration_opens TIME;
ALTER TABLE draft_events ADD COLUMN registration_closes TIME;
ALTER TABLE events ADD COLUMN registration_opens TIME;
ALTER TABLE events ADD COLUMN registration_closes TIME;

-- Suppression du lien organisateur du formulaire (géré via Mon compte > Organisateur)
ALTER TABLE draft_events DROP COLUMN IF EXISTS organisation;
ALTER TABLE events DROP COLUMN IF EXISTS organisation;

-- Adresse du jour (point de rendez-vous)
ALTER TABLE draft_events ADD COLUMN IF NOT EXISTS meeting_name VARCHAR(255);
ALTER TABLE draft_events ADD COLUMN IF NOT EXISTS meeting_address VARCHAR(255);
ALTER TABLE draft_events ADD COLUMN IF NOT EXISTS meeting_coordinates VARCHAR(255);

ALTER TABLE events ADD COLUMN IF NOT EXISTS meeting_name VARCHAR(255);
ALTER TABLE events ADD COLUMN IF NOT EXISTS meeting_address VARCHAR(255);
ALTER TABLE events ADD COLUMN IF NOT EXISTS meeting_coordinates VARCHAR(255);

-- Tables de liaison catégories (si elles n'existent pas encore)
CREATE TABLE IF NOT EXISTS event_category_links (
    event_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    PRIMARY KEY (event_id, category_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS draft_event_category_links (
    draft_event_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    PRIMARY KEY (draft_event_id, category_id),
    FOREIGN KEY (draft_event_id) REFERENCES draft_events(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE CASCADE
);

COMMIT;
