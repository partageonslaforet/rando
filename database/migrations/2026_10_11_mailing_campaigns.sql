-- Campagnes de publipostage enregistrées (objet + corps réutilisables).
-- Utilisées par la section "Campagne" et le bouton email de chaque contact.
CREATE TABLE IF NOT EXISTS mailing_campaigns (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject    VARCHAR(255) NOT NULL,
    body       MEDIUMTEXT   NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
