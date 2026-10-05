-- Migration: Type de notification pour les abonnés
-- Date: 2026-10-05
-- Description: Permet de notifier un abonné à la fois pour un nouvel événement
--              ('new') et pour une modification ('update'). La clé unique
--              (subscriber_id, event_id) empêchait toute notification
--              de modification après la première.

ALTER TABLE `subscriber_notifications`
    DROP INDEX `subscriber_event`,
    ADD COLUMN `notification_type` ENUM('new', 'update') NOT NULL DEFAULT 'new' AFTER `event_id`,
    ADD UNIQUE KEY `subscriber_event_type` (`subscriber_id`, `event_id`, `notification_type`);
