<?php
// Détection de l'environnement
$isProduction = strpos($_SERVER['HTTP_HOST'], 'rando.partageonslaforet.be') !== false;

// Configuration de la base de données selon l'environnement
if ($isProduction) {
    // Configuration de production
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'cool5792_sports_events');
    define('DB_USER', 'cool5792_oconrard');
    define('DB_PASS', 'Armand010cnr');
    define('DB_PORT', '3306');
} else {
    // Configuration de développement (MAMP)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'cool5792_sports_events');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
    define('DB_PORT', '8889');  // Port MAMP MySQL
}

// Autres constantes de l'application
define('APP_URL', $isProduction ? 'https://rando.partageonslaforet.be' : 'http://localhost:8888');
define('APP_NAME', 'Partageons La Forêt');
define('DEBUG', !$isProduction);

// Fonction de connexion à la base de données
function getConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Construction du DSN
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        // Options PDO
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // Création de la connexion
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Test de la connexion
        $pdo->query('SELECT 1');
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion à la base de données: " . $e->getMessage());
        error_log("DSN: " . $dsn);
        error_log("Utilisateur: " . DB_USER);
        error_log("Host: " . DB_HOST);
        error_log("Port: " . DB_PORT);
        throw new PDOException("Erreur de connexion à la base de données: " . $e->getMessage());
    }
}

try {
    $pdo = getConnection();

    // Log de succès
    if (DEBUG) {
        error_log("Connexion à la base de données réussie (" . DB_NAME . ")");
    }
} catch (PDOException $e) {
    if (DEBUG) {
        // Log détaillé de l'erreur
        error_log("Erreur de connexion à la base de données : " . $e->getMessage());
        error_log("DSN : " . $e->getPrevious()->dsn);
        error_log("Utilisateur : " . DB_USER);
        error_log("Environnement : " . ($isProduction ? 'production' : 'développement'));
        
        // Affichage des détails de l'erreur
        echo "Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
        echo "DSN : " . $e->getPrevious()->dsn . "\n";
        echo "Utilisateur : " . DB_USER . "\n";
        echo "Environnement : " . ($isProduction ? 'production' : 'développement') . "\n";
    } else {
        // Log détaillé de l'erreur
        error_log("Erreur de connexion à la base de données : " . $e->getMessage());
        error_log("DSN : " . $e->getPrevious()->dsn);
        error_log("Utilisateur : " . DB_USER);
        error_log("Environnement : " . ($isProduction ? 'production' : 'développement'));
        
        // En production, ne pas afficher les détails de l'erreur
        echo "Une erreur est survenue lors de la connexion à la base de données.";
    }
    exit;
}
