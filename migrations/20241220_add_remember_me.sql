-- Ajout des colonnes pour la fonctionnalité "Se souvenir de moi"
ALTER TABLE users
ADD COLUMN remember_token VARCHAR(100) NULL,
ADD COLUMN remember_token_expires_at DATETIME NULL;
