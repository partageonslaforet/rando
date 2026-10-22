-- Contacts de publipostage (publipostage admin) — ex. calendrier clubs VTT/cyclos.
-- Une ligne par adresse email unique ; un club à plusieurs emails = plusieurs lignes.
-- status = 0 → désinscrit (exclu des campagnes, RGPD).
CREATE TABLE IF NOT EXISTS mailing_contacts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    club         VARCHAR(160) NOT NULL,
    contact_name VARCHAR(160) DEFAULT NULL,
    email        VARCHAR(190) NOT NULL,
    phone        VARCHAR(60)  DEFAULT NULL,
    lieu         VARCHAR(160) DEFAULT NULL,
    type         VARCHAR(80)  DEFAULT NULL,
    status       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT NULL,
    UNIQUE KEY uq_mailing_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
