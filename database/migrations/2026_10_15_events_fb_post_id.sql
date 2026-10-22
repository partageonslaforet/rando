-- 2026-10-15 — Track publication Facebook par événement
-- fb_post_id : identifiant du post Page FB (ex: "1417442544778292_123456789")
-- NULL = jamais publié sur la Page. Renseigné par postEventToFacebook()
-- (auto à l'approbation d'un nouvel événement + bouton admin manuel).

ALTER TABLE events
    ADD COLUMN fb_post_id VARCHAR(64) NULL DEFAULT NULL AFTER status,
    ADD KEY idx_events_fb_post_id (fb_post_id);
