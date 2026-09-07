-- Ajout de la catégorie aux parcours (brouillons et événements publiés)
ALTER TABLE `draft_parcours` ADD COLUMN `category_id` INT(11) DEFAULT NULL;
ALTER TABLE `event_parcours` ADD COLUMN `category_id` INT(11) DEFAULT NULL;
