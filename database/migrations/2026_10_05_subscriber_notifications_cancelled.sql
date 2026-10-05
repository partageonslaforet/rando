-- Migration: Type 'cancelled' pour les notifications abonnés
-- Date: 2026-10-05
-- Description: Ajoute le type 'cancelled' afin de notifier les abonnés
--              lorsqu'un événement est annulé (is_cancelled = 1).

ALTER TABLE `subscriber_notifications`
    MODIFY COLUMN `notification_type` ENUM('new', 'update', 'cancelled') NOT NULL DEFAULT 'new';
