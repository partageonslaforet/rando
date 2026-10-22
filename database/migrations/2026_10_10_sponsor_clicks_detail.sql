-- Enrichit sponsor_clicks avec les mêmes données que site_visits :
-- IP, session, user-agent, referrer et flag humain/non-humain.
-- Les lignes existantes héritent de is_human = 1 (défaut).
ALTER TABLE sponsor_clicks
    ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER ip_hash,
    ADD COLUMN session_id VARCHAR(64) DEFAULT NULL AFTER ip_address,
    ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER session_id,
    ADD COLUMN referrer   VARCHAR(255) DEFAULT NULL AFTER user_agent,
    ADD COLUMN is_human   TINYINT(1) NOT NULL DEFAULT 1 AFTER referrer;
