<?php
// Afficher toutes les erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test de connexion à la base de données...\n";

try {
    // Charger la configuration
    require_once __DIR__ . '/../includes/config.php';
    echo "Configuration chargée\n";
    
    // Afficher les paramètres de connexion
    echo "Paramètres de connexion :\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Port: " . DB_PORT . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo "User: " . DB_USER . "\n";
    
    // Tenter la connexion
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    echo "DSN: " . $dsn . "\n";
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connexion réussie!\n";
    
    // Test de requête simple
    $stmt = $pdo->query('SELECT 1');
    $result = $stmt->fetch();
    echo "Requête test exécutée avec succès\n";
    
} catch (Exception $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
    echo "Type d'erreur : " . get_class($e) . "\n";
    echo "Trace :\n" . $e->getTraceAsString() . "\n";
}
