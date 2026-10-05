-- Supprime la colonne redondante `category` de la table events
-- en récupérant au préalable le category_id correct depuis le code texte

UPDATE `events` e
SET e.category_id = (
    SELECT c.id
    FROM `event_categories` c
    WHERE c.code = e.category
)
WHERE e.category IS NOT NULL
  AND (e.category_id IS NULL OR e.category_id NOT IN (SELECT id FROM `event_categories`));

ALTER TABLE `events` DROP COLUMN `category`;
