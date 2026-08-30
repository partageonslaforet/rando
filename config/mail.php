<?php

// Configuration e-mail depuis l'environnement
// Aucun secret ne doit être stocké ici.
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'localhost');
define('MAIL_PORT', (int)($_ENV['MAIL_PORT'] ?? 1025));
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD'] ?? '');
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? '');
define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@partageonslaforet.be');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Partageons la Forêt');
