<?php
// Définition des chemins
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');

// Détection de l'environnement
$isProduction = strpos($_SERVER['HTTP_HOST'] ?? '', 'partageonslaforet.be') !== false;

// Chargement des classes et fonctions
require_once CONFIG_PATH . '/database.php';
// Charger d'abord une éventuelle surcharge locale, puis la config globale mail
if (file_exists(CONFIG_PATH . '/mail.local.php')) {
    require_once CONFIG_PATH . '/mail.local.php';
}
require_once CONFIG_PATH . '/mail.php';  // Ajout de la configuration mail
require_once INCLUDES_PATH . '/functions.php';

// Configuration du fuseau horaire
date_default_timezone_set('Europe/Brussels');

// Fonctions de formatage
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function sanitize($input) {
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}
