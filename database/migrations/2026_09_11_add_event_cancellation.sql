-- Migration : ajout de l'annulation d'événements
-- À exécuter sur PostgreSQL

ALTER TABLE events
    ADD COLUMN is_cancelled BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN cancelled_at TIMESTAMP NULL,
    ADD COLUMN cancellation_reason TEXT NULL;
