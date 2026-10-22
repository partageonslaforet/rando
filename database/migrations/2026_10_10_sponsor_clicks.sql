-- Compteur de clics sur les sponsors + détail horodaté par emplacement.
-- Compteur dénormalisé pour un affichage admin sans jointure.
ALTER TABLE sponsors
    ADD COLUMN click_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER end_date;

-- Détail des clics : une ligne par clic (stats par date et par emplacement).
-- ip_hash = empreinte SHA-256 de l'adresse IP (pas d'IP en clair — RGPD).
-- Pas de FK volontaire : les stats survivent à la suppression d'un sponsor.
CREATE TABLE IF NOT EXISTS sponsor_clicks (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sponsor_id  INT NOT NULL,
    context     VARCHAR(20) NOT NULL DEFAULT 'unknown' COMMENT 'footer | event',
    ip_hash     CHAR(64) DEFAULT NULL,
    clicked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sponsor (sponsor_id),
    INDEX idx_clicked (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
