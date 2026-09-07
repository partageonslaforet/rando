<?php

// Configuration e-mail depuis l'environnement
// Aucun secret ne doit être stocké ici. Chaque constante est définie uniquement si absente.
if (!defined('MAIL_HOST'))       define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'localhost');
if (!defined('MAIL_PORT'))       define('MAIL_PORT', (int)($_ENV['MAIL_PORT'] ?? 1025));
if (!defined('MAIL_USERNAME'))   define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
if (!defined('MAIL_PASSWORD'))   define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
if (!defined('MAIL_ENCRYPTION')) define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? '');
if (!defined('MAIL_FROM_EMAIL')) define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@partageonslaforet.be');
if (!defined('MAIL_FROM_NAME'))  define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Partageons la Forêt');

// Destination par défaut pour le formulaire de contact
if (!defined('CONTACT_TO_EMAIL')) define('CONTACT_TO_EMAIL', $_ENV['CONTACT_TO_EMAIL'] ?? 'rando@partageonslaforet.be');
if (!defined('CONTACT_TO_NAME'))  define('CONTACT_TO_NAME',  $_ENV['CONTACT_TO_NAME']  ?? 'Partageons la Forêt');
