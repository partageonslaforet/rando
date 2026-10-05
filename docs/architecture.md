# Architecture cible — rando.partageonslaforet.be

```text
rando.partageonslaforet.be/
│
├── public/                         # Seul dossier accessible depuis le web
│   ├── index.php                   # Front controller unique
│   ├── .htaccess                   # Réécriture des routes vers index.php
│   └── assets/
│       ├── css/
│       │   ├── app.css             # Styles généraux
│       │   ├── components/         # Header, filtres, calendrier, cartes…
│       │   └── pages/              # events.css, auth.css, profile.css…
│       ├── js/
│       │   ├── app.js              # JS commun
│       │   └── pages/              # events.js, auth.js, event-edit.js…
│       ├── images/                 # Logos, icônes, images fixes
│       └── fonts/
│
├── config/                         # Configuration sans secret
│   ├── app.php                     # APP_URL, environnement, debug
│   ├── database.php                # PDO depuis .env
│   ├── mail.php                    # PHPMailer depuis .env
│   └── storage.php                 # Chemins de stockage
│
├── routes/
│   ├── web.php                     # Routes des pages HTML
│   └── api.php                     # Routes JSON / AJAX
│
├── src/                            # Code PHP métier — namespace App\
│   ├── Controllers/
│   │   ├── Web/                    # Accueil, événements, profil, auth
│   │   ├── Api/                    # Endpoints JSON
│   │   └── Admin/                  # Administration et validation
│   ├── Models/                     # Event, User, Category, Organization…
│   ├── Services/                   # Auth, Event, Mail, Upload, GPX…
│   ├── Repositories/               # Requêtes PDO centralisées
│   ├── Middleware/                 # Session, CSRF, rôle, admin, rate limit
│   ├── Mail/                       # Génération des e-mails
│   └── Support/                    # Helpers, validation, réponses HTTP
│
├── templates/
│   ├── layouts/
│   │   ├── main.php                # Header/footer public uniques
│   │   ├── auth.php
│   │   └── admin.php
│   ├── components/                 # Navigation, flash, filtres, map…
│   ├── pages/
│   │   ├── home.php
│   │   ├── events/
│   │   ├── auth/
│   │   ├── profile/
│   │   └── admin/
│   └── emails/
│       ├── verification.php
│       ├── password-reset.php
│       └── event-published.php
│
├── database/
│   ├── migrations/                 # Une modification de schéma par fichier
│   ├── seeds/                      # Données de démonstration
│   └── bootstrap.sql               # Installation locale complète
│
├── storage/                        # Jamais accessible directement par le web
│   ├── uploads/
│   │   ├── events/
│   │   ├── organizers/
│   │   └── gpx/
│   ├── logs/
│   ├── sessions/
│   └── cache/
│
├── tests/
│   ├── Feature/                    # Login, inscription, événements…
│   ├── Unit/
│   └── Fixtures/
│
├── docs/
│   ├── architecture.md
│   ├── installation.md
│   └── deployment.md
│
├── .env                            # Local, jamais versionné
├── .env.example                    # Modèle sans secrets
├── .gitignore
├── composer.json
├── composer.lock
└── README.md
```

## Fonctionnement

```text
Navigateur
  → public/.htaccess
  → public/index.php
  → routes/web.php ou routes/api.php
  → middleware (session, rôle, CSRF)
  → contrôleur
  → service / repository / modèle
  → MySQL
  → template HTML ou réponse JSON
```

## Règles fondamentales

- `public/` est le seul document-root Apache/MAMP/production.
- Il n’existe qu’un seul header/footer par type de page : public, authentification, administration.
- Les CSS et JS ne sont jamais dupliqués entre `assets/`, `templates/js/` et `public/assets/`.
- `vendor/` est généré par Composer et ne va pas dans Git.
- Les secrets sont uniquement dans `.env`.
- Les fichiers uploadés vont dans `storage/`, pas dans les assets.
- L’API ne contient plus de scripts isolés : elle passe par `routes/api.php` et des contrôleurs.
- Les brouillons sont dans `events` avec les statuts `draft`, `pending`, `published`, `rejected`.
- Seuls les événements `published` sont publics.