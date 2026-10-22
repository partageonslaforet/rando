-- Historique des envois mailing (campagnes, unitaires, tests)
-- MySQL : les index sont déclarés inline (CREATE INDEX IF NOT EXISTS n'existe pas en MySQL).
CREATE TABLE IF NOT EXISTS mailing_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED DEFAULT NULL,
    contact_id  INT UNSIGNED DEFAULT NULL,
    email       VARCHAR(190) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    status      VARCHAR(10)  NOT NULL DEFAULT 'ok',
    error       TEXT         DEFAULT NULL,
    is_test     TINYINT(1)   NOT NULL DEFAULT 0,
    sent_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mailing_logs_sent (sent_at),
    KEY idx_mailing_logs_contact (contact_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
