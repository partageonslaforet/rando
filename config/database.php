<?php
require_once __DIR__ . '/../logs/error.log.php';

// Chargement des variables d'environnement
$dotenvPath = __DIR__ . '/../.env';

// Timezone par défaut (sera surchargée après lecture du .env)
date_default_timezone_set('Europe/Brussels');
if (file_exists($dotenvPath)) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        } catch (Throwable $e) {
            logError(__FILE__, 'Dotenv loader failed', ['error' => $e->getMessage()]);
        }
    } elseif (function_exists('parse_ini_file')) {
        $ini = parse_ini_file($dotenvPath, false, INI_SCANNER_RAW);
        if ($ini !== false) {
            foreach ($ini as $key => $value) {
                if (is_string($value)) {
                    $value = trim($value);
                    $value = trim($value, "\"'");
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Timezone réelle après chargement .env
if (!empty($_ENV['APP_TIMEZONE'])) {
    date_default_timezone_set($_ENV['APP_TIMEZONE']);
}

// Détection de l'environnement
$appEnv = strtolower($_ENV['APP_ENV'] ?? '');
$isProduction = $appEnv === 'production' || strpos($_SERVER['HTTP_HOST'] ?? '', 'rando.partageonslaforet.be') !== false;

// Configuration de la base de données
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'cool5792_sports_events');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'root');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');

// Autres constantes de l'application
define('APP_URL', $_ENV['APP_URL'] ?? ($isProduction ? 'https://rando.partageonslaforet.be' : 'http://localhost:8888'));
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Partageons La Forêt');
define('DEBUG', filter_var($_ENV['DEBUG'] ?? !$isProduction, FILTER_VALIDATE_BOOLEAN));

// Construction du DSN
function getDsn() {
    return "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
}

// Fonction de connexion à la base de données
function getConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    $dsn = getDsn();
    
    try {
        // Options PDO
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // Création de la connexion
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Test de la connexion sans afficher le résultat
        $stmt = $pdo->prepare('SELECT 1');
        $stmt->execute();
        $stmt->closeCursor();
        
        return $pdo;
    } catch (PDOException $e) {
        logError(__FILE__, 'PDO connection failed', [
            'message' => $e->getMessage(),
            'dsn' => $dsn,
            'host' => DB_HOST,
            'port' => DB_PORT
        ]);
        throw new PDOException("Erreur de connexion à la base de données");
    }
}

try {
    $pdo = getConnection();

    // Log de succès
    if (DEBUG) {
        error_log("Connexion à la base de données réussie (" . DB_NAME . ")");
    }
} catch (PDOException $e) {
    // Log sécurisé des détails
    logError(__FILE__, 'Database connection error', [
        'message' => $e->getMessage(),
        'dsn' => getDsn(),
        'user' => DB_USER,
        'env' => $isProduction ? 'production' : 'development'
    ]);

    // Affichage des détails en local uniquement, jamais en prod
    if (DEBUG && !$isProduction) {
        echo "Erreur de connexion à la base de données : " . $e->getMessage() . "\n";
        echo "DSN : " . getDsn() . "\n";
        echo "Utilisateur : " . DB_USER . "\n";
        echo "Environnement : " . ($isProduction ? 'production' : 'développement') . "\n";
    } else {
        echo "Une erreur est survenue lors de la connexion à la base de données.";
    }
    exit;
}
