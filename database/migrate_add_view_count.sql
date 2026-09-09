-- Migration : ajouter un compteur de vues par evenement
ALTER TABLE `events` ADD COLUMN `view_count` INT(11) DEFAULT 0;
