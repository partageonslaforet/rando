-- Trace l'événement sur lequel un clic sponsor a été fait (ctx = 'event').
-- NULL pour les clics footer ou les lignes antérieures à cette migration.
ALTER TABLE sponsor_clicks
    ADD COLUMN event_id INT DEFAULT NULL AFTER context,
    ADD INDEX idx_event (event_id);
