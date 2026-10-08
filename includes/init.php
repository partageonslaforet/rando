<?php
/**
 * Fichier d'initialisation global
 * Ce fichier doit être inclus au début de chaque script PHP
 */

// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Charger les fonctions utilitaires
require_once __DIR__ . '/functions.php';
// Logger central
require_once dirname(__DIR__) . '/logs/error.log.php';

// Initialiser la session de manière sécurisée
initSession();

// Charger les configurations
$configFiles = [
    '/config/database.php'
];

foreach ($configFiles as $file) {
    $fullPath = dirname(__DIR__) . $file;
    if (!file_exists($fullPath)) {
        error_log("❌ Fichier de configuration manquant: " . $file);
        http_response_code(500);
        die("Une erreur de configuration est survenue.");
    }
    require_once $fullPath;
}

// Surcharge locale optionnelle pour la configuration mail (non versionnée) — chargée AVANT la config globale
$localMail = dirname(__DIR__) . '/config/mail.local.php';
if (file_exists($localMail)) {
    require_once $localMail;
}

// Charger la configuration mail globale (ne définit que ce qui manque)
require_once dirname(__DIR__) . '/config/mail.php';

// Connexion à la base de données
try {
    error_log("🔄 Tentative de connexion à la base de données...");
    error_log("📊 Paramètres DB: Host=" . DB_HOST . ", DB=" . DB_NAME);
    
    $db = getConnection();
    if (!$db) {
        throw new PDOException("La connexion à la base de données a échoué");
    }
    
    // Test de la connexion sans afficher le résultat
    $stmt = $db->prepare("SELECT 1");
    $stmt->execute();
    $stmt->closeCursor();
    
    error_log("✅ Connexion à la base de données réussie");
    
} catch(PDOException $e) {
    error_log("❌ Erreur de connexion à la base de données : " . $e->getMessage());
    error_log("❌ Trace : " . $e->getTraceAsString());
    
    http_response_code(500);
    die("Une erreur est survenue lors de la connexion à la base de données.");
}

// Charger la vérification d'authentification
require_once __DIR__ . '/auth_check.php';
