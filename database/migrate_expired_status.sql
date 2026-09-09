-- Migration : ajouter le statut 'expired' pour le cron d'expiration des evenements
ALTER TABLE `events` MODIFY COLUMN `status` ENUM('draft','published','approved','pending','rejected','expired') DEFAULT 'draft';
