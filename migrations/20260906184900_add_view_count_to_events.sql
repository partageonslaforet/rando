-- Ajoute un compteur de vues aux événements publiés
ALTER TABLE events ADD COLUMN view_count INT UNSIGNED NOT NULL DEFAULT 0;
