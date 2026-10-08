-- Migration: notification organisateur + revendication d'événement (MySQL)
-- Usage: notification manuelle depuis le dashboard admin (organizer_notified_at)
--        + lien tokenisé dans l'email pour que l'organisateur revendique son événement (claim_token)
-- Note: MySQL ne supporte pas "IF NOT EXISTS" sur ADD COLUMN — à exécuter une seule fois.
--       Si la colonne existe déjà, l'erreur "Duplicate column name" peut être ignorée.

ALTER TABLE events
  ADD COLUMN organizer_notified_at DATETIME NULL;

ALTER TABLE events
  ADD COLUMN claim_token VARCHAR(64) NULL;

ALTER TABLE events
  ADD INDEX idx_events_claim_token (claim_token);
