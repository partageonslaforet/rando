Plan déploiement via FileZilla
Préparer l’hébergement
Crée un sous-domaine de pré‑prod (ex: v2.rando.partageonslaforet.be) et règle son DocumentRoot vers le dossier public de la nouvelle app.
Idéal: dossier serveur ~/sites/rando_v2 avec docroot ~/sites/rando_v2/public.
Évite d’écraser le site live pendant les tests.
Active HTTPS (AutoSSL/Let’s Encrypt) sur le sous‑domaine.
Configurer FileZilla
Protocole: SFTP si possible (sinon FTPS explicite).
Paramètres: hôte, port, utilisateur, mot de passe fournis par O2switch.
Réglages: cocher “preserve timestamps”, mode binaire/auto, limiter connexions simultanées (3‑5) pour éviter des erreurs.
Uploader les fichiers
Côté serveur: crée un dossier rando_v2 hors public_html si possible (ex: home/USER/sites/rando_v2).
Envoie l’intégralité du projet dedans, en conservant l’arborescence (le web root doit être public/).
Ne pousse pas de secrets en clair dans des fichiers publics (.env, etc.). Place ta config sensible hors public/.
Config prod (proposée)
Un fichier de config central (ex: config/config.prod.php, inclus par public/index.php) avec:


php
<?php
return [
  'app_env' => 'production',
  'app_url' => 'https://v2.rando.partageonslaforet.be',
  'db' => [
    'host' => 'localhost',
    'name' => 'NOM_BD',
    'user' => 'UTILISATEUR',
    'pass' => 'MOTDEPASSE',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
  ],
  'timezone' => 'Europe/Brussels',
  'display_errors' => false,
  'log_errors' => true,
];
On lira cette config dans ton bootstrap PHP (je te fournirai l’inclusion si besoin). Tu valides, puis je te propose le code exact.
Base MySQL — vérification et alignement
Sauvegardes
DB distante (phpMyAdmin → Export complet .sql) avant toute modif.
DB locale (mysqldump) pour référence.
Comparer schémas (simple)
Local: Export “structure uniquement” via phpMyAdmin → schema_local.sql
Distant: Export “structure uniquement” via phpMyAdmin → schema_remote.sql
Ouvre les deux dans un diff (VSCode/WinMerge) et repère les écarts (tables, colonnes, index, collation).
Aligner la DB distante
Option sûre: créer une nouvelle DB (ex: rando_v2), importer le dump local (structure + données).
Option incrémentale: exécuter quelques ALTER TABLE manuels pour combler les écarts (plus risqué si gros delta).
Vérifs rapides SQL (dans phpMyAdmin → SQL)


sql
SHOW VARIABLES LIKE 'character_set_server';
SHOW VARIABLES LIKE 'collation_server';
SELECT COUNT(*) AS events FROM events;
SELECT COUNT(*) AS categories FROM categories;
SELECT COUNT(*) AS users FROM users;
Attendu: utf8mb4 / utf8mb4_unicode_ci et des volumes proches du local.
Tests de pré‑prod (fichiers et liens)
Ouvre v2.rando.partageonslaforet.be
DevTools → Network: filtre “.js” et “.css” → vérifier 0 requête 404.
Console: 0 erreur JS.
Pages à tester:
Accueil: filtres, recherche, calendrier, carte Leaflet OK.
Détail événement: carte + GPX OK (on a corrigé leaflet-gpx en CDN).
Création/Édition: étapes, validations, upload images, carte/GPX OK.
Admin: dashboard (pills, recherche), catégories (CRUD).
Espace utilisateur: Mes événements (brouillon), Profil (maj).
Si tout est OK, bascule en prod:
Dans cPanel, change le DocumentRoot du domaine principal vers ~/sites/rando_v2/public (swap instantané et réversible).
Garde l’ancienne app à côté quelques jours (rollback facile).
Contrôles finaux prod
HTTPS forcé via .htaccess (301).
display_errors=Off, log_errors=On. Vérifier logs/error.log.php reçoit bien via logError().
Retest rapide des pages clés, Network/Console propres.
Ce dont j’ai besoin pour t’assister au plus vite
Chemin cible exact côté O2switch (où tu veux mettre rando_v2).
Voulez‑vous créer une nouvelle DB (recommandé) ou réutiliser l’existante ?
Les identifiants MySQL (ou me dire si on laisse des placeholders et tu les renseignes toi‑même).
GO pour te proposer le snippet d’inclusion config.prod.php dans le bootstrap (je te montre le code avant toute modif).
Dès que tu confirmes ces points, je te prépare:

Le squelette config/config.prod.php et l’inclusion non‑intrusive côté app.
Une courte check‑list “FileZilla pas à pas” (captures si besoin).
Un micro script “db‑ping” protégé (optionnel) pour valider la connexion MySQL en prod et loguer via logError().