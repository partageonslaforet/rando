-- Migration: Add manage_token columns to event_subscribers (MySQL/MariaDB)

-- Ajoute la colonne manage_token si absente
ALTER TABLE event_subscribers
  ADD COLUMN IF NOT EXISTS manage_token VARCHAR(128) NULL;

-- Ajoute la colonne manage_token_expires si absente
ALTER TABLE event_subscribers
  ADD COLUMN IF NOT EXISTS manage_token_expires DATETIME NULL;

-- Index pour les recherches par token
CREATE INDEX IF NOT EXISTS idx_event_subscribers_manage_token
  ON event_subscribers (manage_token);
