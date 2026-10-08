-- Période de visibilité des sponsors
-- NULL = pas de borne : un sponsor sans dates reste visible si active = 1 (rétrocompatible).
ALTER TABLE sponsors
    ADD COLUMN start_date DATE DEFAULT NULL AFTER active,
    ADD COLUMN end_date   DATE DEFAULT NULL AFTER start_date;
