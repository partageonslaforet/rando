--- Migration : ajout du flag is_human pour distinguer humains vs outils/bots
--- À exécuter sur MySQL (production et développement)

ALTER TABLE `site_visits`
    ADD COLUMN `is_human` TINYINT(1) NOT NULL DEFAULT 1;
