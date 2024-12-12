<?php
// Chargement des variables d'environnement
require_once __DIR__ . '/.env.php';

// Configuration des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

// Configuration CORS
$allowed_origins = [
    'http://localhost:5173',
    'https://rando.partageonslaforet.be',
    'https://www.rando.partageonslaforet.be'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 3600');
    header('Vary: Origin');
}

// Gestion des requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (in_array($origin, $allowed_origins)) {
        http_response_code(204);
    } else {
        http_response_code(403);
    }
    exit();
}

// Chargement des dépendances externes
require_once __DIR__ . '/../../vendor/autoload.php';

// Chargement des configurations
require_once __DIR__ . '/database.php';

// Chargement des utilitaires de base
require_once __DIR__ . '/../includes/Logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../utils/functions.php';
require_once __DIR__ . '/../utils/mailer.php';

// Chargement des modèles
require_once __DIR__ . '/../models/User.php';

// Chargement des middlewares
require_once __DIR__ . '/../includes/auth_middleware.php';

// Chargement des contrôleurs
require_once __DIR__ . '/../controllers/AuthController.php';